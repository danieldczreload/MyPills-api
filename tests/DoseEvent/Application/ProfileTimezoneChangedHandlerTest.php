<?php

declare(strict_types=1);

namespace App\Tests\DoseEvent\Application;

use DoseEvent\Application\Event\ProfileTimezoneChangedHandler;
use DoseEvent\Application\MaterializeUpcomingOccurrences;
use DoseEvent\Domain\DoseEventExpander;
use DoseEvent\Domain\DoseEventRepository;
use DoseEvent\Domain\DoseEventsExpandedEvent;
use Medication\Domain\Medication;
use Medication\Domain\MedicationRepository;
use PHPUnit\Framework\TestCase;
use Profile\Domain\ProfileTimezoneChangedEvent;
use Schedule\Domain\DailySchedule;
use Schedule\Domain\ScheduleRepository;
use Schedule\Domain\ValueObject\TimeOfDay;
use Shared\Application\Bus\EventBus;
use Shared\Domain\ValueObject\MedicationId;
use Shared\Domain\ValueObject\ProfileId;
use Shared\Domain\ValueObject\ScheduleId;

final class ProfileTimezoneChangedHandlerTest extends TestCase
{
    public function testDeletesPendingAndReexpandsActiveSchedules(): void
    {
        $profileId = new ProfileId('prof-1');
        $medicationId = new MedicationId('med-1');
        $scheduleId = new ScheduleId('sch-1');
        $schedule = new DailySchedule(
            $scheduleId,
            $medicationId,
            [new TimeOfDay(16, 25)],
            new \DateTimeImmutable('2026-09-01'),
            null,
            null,
            new \DateTimeImmutable(),
            new \DateTimeImmutable()
        );

        $medicationRepo = $this->createMock(MedicationRepository::class);
        $medicationRepo->method('findByProfileId')->willReturn([
            Medication::create($medicationId, $profileId, 'Aspirin'),
        ]);
        $scheduleRepo = $this->createMock(ScheduleRepository::class);
        $scheduleRepo->method('findByMedicationIds')->willReturn([$schedule]);
        $doseRepo = $this->createMock(DoseEventRepository::class);
        $doseRepo->expects(self::once())->method('deletePendingByScheduleIds')->with([$scheduleId]);
        $doseRepo->method('findByScheduleIdsAndRange')->willReturn([]);
        $doseRepo->expects(self::atLeastOnce())->method('save');
        $eventBus = $this->createMock(EventBus::class);
        $eventBus->expects(self::once())->method('publish')->with(self::callback(
            static function (object $event): bool {
                return $event instanceof DoseEventsExpandedEvent
                    && $event->profileId === 'prof-1';
            }
        ));

        $handler = new ProfileTimezoneChangedHandler(
            $medicationRepo,
            $scheduleRepo,
            $doseRepo,
            new MaterializeUpcomingOccurrences($doseRepo, new DoseEventExpander()),
            $eventBus
        );
        $handler(new ProfileTimezoneChangedEvent('prof-1', 'UTC', 'America/El_Salvador'));
    }

    public function testDoesNotReexpandCancelledSchedules(): void
    {
        $profileId = new ProfileId('prof-1');
        $medicationId = new MedicationId('med-1');
        $schedule = new DailySchedule(
            new ScheduleId('sch-1'),
            $medicationId,
            [new TimeOfDay(8, 0)],
            new \DateTimeImmutable('2026-09-01'),
            null,
            null,
            new \DateTimeImmutable(),
            new \DateTimeImmutable()
        );
        $schedule->markCancelled(new \DateTimeImmutable());

        $medicationRepo = $this->createMock(MedicationRepository::class);
        $medicationRepo->method('findByProfileId')->willReturn([
            Medication::create($medicationId, $profileId, 'Aspirin'),
        ]);
        $scheduleRepo = $this->createMock(ScheduleRepository::class);
        $scheduleRepo->method('findByMedicationIds')->willReturn([$schedule]);
        $doseRepo = $this->createMock(DoseEventRepository::class);
        $doseRepo->expects(self::never())->method('deletePendingByScheduleIds');
        $doseRepo->expects(self::never())->method('save');
        $eventBus = $this->createMock(EventBus::class);
        $eventBus->expects(self::never())->method('publish');

        $handler = new ProfileTimezoneChangedHandler(
            $medicationRepo,
            $scheduleRepo,
            $doseRepo,
            new MaterializeUpcomingOccurrences($doseRepo, new DoseEventExpander()),
            $eventBus
        );
        $handler(new ProfileTimezoneChangedEvent('prof-1', 'UTC', 'America/El_Salvador'));
    }
}
