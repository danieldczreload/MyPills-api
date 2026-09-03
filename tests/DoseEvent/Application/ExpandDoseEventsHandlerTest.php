<?php

declare(strict_types=1);

namespace App\Tests\DoseEvent\Application;

use DoseEvent\Application\Command\ExpandDoseEventsCommand;
use DoseEvent\Application\Command\ExpandDoseEventsHandler;
use DoseEvent\Application\MaterializeUpcomingOccurrences;
use DoseEvent\Domain\DoseEvent;
use DoseEvent\Domain\DoseEventExpander;
use DoseEvent\Domain\DoseEventRepository;
use DoseEvent\Domain\DoseEventsExpandedEvent;
use Medication\Domain\Medication;
use Medication\Domain\MedicationRepository;
use PHPUnit\Framework\TestCase;
use Profile\Domain\PatientProfile;
use Profile\Domain\ProfileRepository;
use Schedule\Domain\DailySchedule;
use Schedule\Domain\ScheduleRepository;
use Schedule\Domain\ValueObject\TimeOfDay;
use Shared\Application\Bus\EventBus;
use Shared\Domain\ValueObject\DoseEventId;
use Shared\Domain\ValueObject\MedicationId;
use Shared\Domain\ValueObject\ProfileId;
use Shared\Domain\ValueObject\ScheduleId;
use Shared\Domain\ValueObject\UserId;

final class ExpandDoseEventsHandlerTest extends TestCase
{
    public function testCreatesMissingOccurrencesAndPublishesOneEventPerProfile(): void
    {
        $scheduleRepo = $this->createMock(ScheduleRepository::class);
        $doseEventRepo = $this->createMock(DoseEventRepository::class);
        $medicationRepo = $this->createMock(MedicationRepository::class);
        $profileRepo = $this->createMock(ProfileRepository::class);
        $eventBus = $this->createMock(EventBus::class);

        $medicationId = new MedicationId('223e4567-e89b-12d3-a456-426614174000');
        $scheduleA = new DailySchedule(
            new ScheduleId('123e4567-e89b-12d3-a456-426614174000'),
            $medicationId,
            [new TimeOfDay(8, 0)],
            new \DateTimeImmutable('2026-09-01'),
            null,
            null,
            new \DateTimeImmutable(),
            new \DateTimeImmutable()
        );
        $scheduleB = new DailySchedule(
            new ScheduleId('323e4567-e89b-12d3-a456-426614174000'),
            $medicationId,
            [new TimeOfDay(20, 0)],
            new \DateTimeImmutable('2026-09-01'),
            null,
            null,
            new \DateTimeImmutable(),
            new \DateTimeImmutable()
        );
        $scheduleRepo->method('findAll')->willReturn([$scheduleA, $scheduleB]);
        $doseEventRepo->method('findByScheduleIdsAndRange')->willReturn([]);
        $doseEventRepo->expects(self::atLeastOnce())->method('save');

        $medicationRepo->method('findById')->willReturn(Medication::create(
            $medicationId,
            new ProfileId('prof-1'),
            'Aspirin'
        ));
        $profileRepo->method('findById')->willReturn(new PatientProfile(
            new ProfileId('prof-1'),
            new UserId('acc-1'),
            'Name',
            new \DateTimeImmutable('1990-01-01'),
            'male',
            null,
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
            'America/El_Salvador'
        ));

        $eventBus->expects(self::once())->method('publish')->with(self::callback(
            static function (object $event): bool {
                return $event instanceof DoseEventsExpandedEvent
                    && $event->profileId === 'prof-1';
            }
        ));

        $handler = new ExpandDoseEventsHandler(
            $scheduleRepo,
            new MaterializeUpcomingOccurrences($doseEventRepo, new DoseEventExpander()),
            $medicationRepo,
            $profileRepo,
            $eventBus
        );
        $result = $handler(new ExpandDoseEventsCommand(new \DateTimeImmutable('2026-09-02T12:00:00Z')));

        self::assertTrue($result->isSuccess());
        /** @var array{schedulesScanned: int, doseEventsCreated: int, profilesQueuedForCalendarSync: int} $data */
        $data = $result->getValue();
        self::assertSame(2, $data['schedulesScanned']);
        self::assertGreaterThan(0, $data['doseEventsCreated']);
        self::assertSame(1, $data['profilesQueuedForCalendarSync']);
    }

    public function testDoesNotPublishWhenAllOccurrencesAlreadyExist(): void
    {
        $scheduleRepo = $this->createMock(ScheduleRepository::class);
        $doseEventRepo = $this->createMock(DoseEventRepository::class);
        $medicationRepo = $this->createMock(MedicationRepository::class);
        $profileRepo = $this->createMock(ProfileRepository::class);
        $eventBus = $this->createMock(EventBus::class);

        $now = new \DateTimeImmutable('2026-09-02T12:00:00Z');
        $medicationId = new MedicationId('223e4567-e89b-12d3-a456-426614174000');
        $scheduleId = new ScheduleId('123e4567-e89b-12d3-a456-426614174000');
        $schedule = new DailySchedule(
            $scheduleId,
            $medicationId,
            [new TimeOfDay(8, 0)],
            new \DateTimeImmutable('2026-09-01'),
            new \DateTimeImmutable('2026-09-02'),
            null,
            new \DateTimeImmutable(),
            new \DateTimeImmutable()
        );
        $scheduleRepo->method('findAll')->willReturn([$schedule]);

        $expander = new DoseEventExpander();
        $existing = $expander->expand(
            $schedule,
            $now,
            $now->modify('+14 days'),
            new \DateTimeZone('America/El_Salvador')
        );
        $doseEventRepo->method('findByScheduleIdsAndRange')->willReturn($existing);
        $doseEventRepo->expects(self::never())->method('save');

        $medicationRepo->method('findById')->willReturn(Medication::create(
            $medicationId,
            new ProfileId('prof-1'),
            'Aspirin'
        ));
        $profileRepo->method('findById')->willReturn(new PatientProfile(
            new ProfileId('prof-1'),
            new UserId('acc-1'),
            'Name',
            new \DateTimeImmutable('1990-01-01'),
            'male',
            null,
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
            'America/El_Salvador'
        ));
        $eventBus->expects(self::never())->method('publish');

        $handler = new ExpandDoseEventsHandler(
            $scheduleRepo,
            new MaterializeUpcomingOccurrences($doseEventRepo, $expander),
            $medicationRepo,
            $profileRepo,
            $eventBus
        );
        $result = $handler(new ExpandDoseEventsCommand($now));

        self::assertTrue($result->isSuccess());
        /** @var array{doseEventsCreated: int, profilesQueuedForCalendarSync: int} $data */
        $data = $result->getValue();
        self::assertSame(0, $data['doseEventsCreated']);
        self::assertSame(0, $data['profilesQueuedForCalendarSync']);
    }

    public function testSkipsUnknownSchedulesWithoutPublishing(): void
    {
        $scheduleRepo = $this->createMock(ScheduleRepository::class);
        $doseEventRepo = $this->createMock(DoseEventRepository::class);
        $eventBus = $this->createMock(EventBus::class);

        $scheduleRepo->method('findAll')->willReturn([]);
        $doseEventRepo->expects(self::never())->method('save');
        $eventBus->expects(self::never())->method('publish');

        $handler = new ExpandDoseEventsHandler(
            $scheduleRepo,
            new MaterializeUpcomingOccurrences($doseEventRepo, new DoseEventExpander()),
            $this->createMock(MedicationRepository::class),
            $this->createMock(ProfileRepository::class),
            $eventBus
        );
        $result = $handler(new ExpandDoseEventsCommand());

        self::assertTrue($result->isSuccess());
        /** @var array{schedulesScanned: int} $data */
        $data = $result->getValue();
        self::assertSame(0, $data['schedulesScanned']);
    }

    public function testDoesNotTreatExistingUtcHydratedOccurrenceAsNew(): void
    {
        $scheduleRepo = $this->createMock(ScheduleRepository::class);
        $doseEventRepo = $this->createMock(DoseEventRepository::class);
        $eventBus = $this->createMock(EventBus::class);

        $now = new \DateTimeImmutable('2026-09-02T12:00:00Z');
        $medicationId = new MedicationId('223e4567-e89b-12d3-a456-426614174000');
        $scheduleId = new ScheduleId('123e4567-e89b-12d3-a456-426614174000');
        $schedule = new DailySchedule(
            $scheduleId,
            $medicationId,
            [new TimeOfDay(16, 25)],
            new \DateTimeImmutable('2026-09-02'),
            new \DateTimeImmutable('2026-09-02'),
            null,
            new \DateTimeImmutable(),
            new \DateTimeImmutable()
        );
        $scheduleRepo->method('findAll')->willReturn([$schedule]);

        $utcOccurrence = DoseEvent::create(
            DoseEventId::generate(),
            $medicationId,
            $scheduleId,
            new \DateTimeImmutable('2026-09-02 22:25:00', new \DateTimeZone('UTC'))
        );
        $doseEventRepo->method('findByScheduleIdsAndRange')->willReturn([$utcOccurrence]);
        $doseEventRepo->expects(self::never())->method('save');
        $eventBus->expects(self::never())->method('publish');

        $medicationRepo = $this->createMock(MedicationRepository::class);
        $medicationRepo->method('findById')->willReturn(Medication::create(
            $medicationId,
            new ProfileId('prof-1'),
            'Aspirin'
        ));
        $profileRepo = $this->createMock(ProfileRepository::class);
        $profileRepo->method('findById')->willReturn(new PatientProfile(
            new ProfileId('prof-1'),
            new UserId('acc-1'),
            'Name',
            new \DateTimeImmutable('1990-01-01'),
            'male',
            null,
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
            'America/El_Salvador'
        ));

        $handler = new ExpandDoseEventsHandler(
            $scheduleRepo,
            new MaterializeUpcomingOccurrences($doseEventRepo, new DoseEventExpander()),
            $medicationRepo,
            $profileRepo,
            $eventBus
        );
        $result = $handler(new ExpandDoseEventsCommand($now));

        self::assertTrue($result->isSuccess());
        /** @var array{doseEventsCreated: int} $data */
        $data = $result->getValue();
        self::assertSame(0, $data['doseEventsCreated']);
    }

    public function testSkipsCancelledSchedules(): void
    {
        $scheduleRepo = $this->createMock(ScheduleRepository::class);
        $doseEventRepo = $this->createMock(DoseEventRepository::class);
        $eventBus = $this->createMock(EventBus::class);

        $schedule = new DailySchedule(
            new ScheduleId('123e4567-e89b-12d3-a456-426614174000'),
            new MedicationId('223e4567-e89b-12d3-a456-426614174000'),
            [new TimeOfDay(8, 0)],
            new \DateTimeImmutable('2026-09-01'),
            null,
            null,
            new \DateTimeImmutable(),
            new \DateTimeImmutable()
        );
        $schedule->markCancelled(new \DateTimeImmutable('2026-09-02T12:00:00Z'));
        $scheduleRepo->method('findAll')->willReturn([$schedule]);
        $doseEventRepo->expects(self::never())->method('save');
        $eventBus->expects(self::never())->method('publish');

        $handler = new ExpandDoseEventsHandler(
            $scheduleRepo,
            new MaterializeUpcomingOccurrences($doseEventRepo, new DoseEventExpander()),
            $this->createMock(MedicationRepository::class),
            $this->createMock(ProfileRepository::class),
            $eventBus
        );
        $result = $handler(new ExpandDoseEventsCommand());

        self::assertTrue($result->isSuccess());
        /** @var array{schedulesScanned: int, doseEventsCreated: int} $data */
        $data = $result->getValue();
        self::assertSame(0, $data['schedulesScanned']);
        self::assertSame(0, $data['doseEventsCreated']);
    }
}
