<?php

declare(strict_types=1);

namespace App\Tests\Schedule\Application;

use Medication\Domain\Medication;
use Medication\Domain\MedicationRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Profile\Domain\PatientProfile;
use Profile\Domain\ProfileRepository;
use Schedule\Application\Command\CreateScheduleCommand;
use Schedule\Application\Command\CreateScheduleHandler;
use Schedule\Domain\DailySchedule;
use Schedule\Domain\Schedule;
use Schedule\Domain\ScheduleCreatedEvent;
use Schedule\Domain\ScheduleRepository;
use Schedule\Domain\ValueObject\TimeOfDay;
use Shared\Application\Bus\EventBus;
use Shared\Domain\ValueObject\MedicationId;
use Shared\Domain\ValueObject\ProfileId;
use Shared\Domain\ValueObject\ScheduleId;
use Shared\Domain\ValueObject\UserId;

final class CreateScheduleHandlerTest extends TestCase
{
    private ScheduleRepository&MockObject $scheduleRepo;
    private MedicationRepository&MockObject $medicationRepo;
    private ProfileRepository&MockObject $profileRepo;
    private EventBus&MockObject $eventBus;
    private CreateScheduleHandler $handler;

    protected function setUp(): void
    {
        $this->scheduleRepo = $this->createMock(ScheduleRepository::class);
        $this->medicationRepo = $this->createMock(MedicationRepository::class);
        $this->profileRepo = $this->createMock(ProfileRepository::class);
        $this->eventBus = $this->createMock(EventBus::class);

        $this->handler = new CreateScheduleHandler(
            $this->scheduleRepo,
            $this->medicationRepo,
            $this->profileRepo,
            $this->eventBus
        );
    }

    public function testProfileNotFoundReturnsFailure(): void
    {
        $this->profileRepo->method('findById')->willReturn(null);
        $cmd = new CreateScheduleCommand('prof-1', 'acc-1', 'med-1', 'daily', new \DateTimeImmutable(), null, [['hour' => 8, 'minute' => 0]]);

        $result = ($this->handler)($cmd);
        self::assertTrue($result->isFailure());
        self::assertSame('Profile not found.', $result->getFailure()->getMessage());
    }

    public function testProfileForbiddenWhenNotOwned(): void
    {
        $profile = new PatientProfile(new ProfileId('prof-1'), new UserId('acc-other'), 'Name', new \DateTimeImmutable('1990-01-01'), 'male', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $this->profileRepo->method('findById')->willReturn($profile);

        $cmd = new CreateScheduleCommand('prof-1', 'acc-1', 'med-1', 'daily', new \DateTimeImmutable(), null, [['hour' => 8, 'minute' => 0]]);
        $result = ($this->handler)($cmd);
        self::assertTrue($result->isFailure());
        self::assertSame('You do not own this profile.', $result->getFailure()->getMessage());
    }

    public function testMedicationNotFoundReturnsFailure(): void
    {
        $profile = new PatientProfile(new ProfileId('prof-1'), new UserId('acc-1'), 'Name', new \DateTimeImmutable('1990-01-01'), 'male', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $this->profileRepo->method('findById')->willReturn($profile);
        $this->medicationRepo->method('findById')->willReturn(null);

        $cmd = new CreateScheduleCommand('prof-1', 'acc-1', 'med-1', 'daily', new \DateTimeImmutable(), null, [['hour' => 8, 'minute' => 0]]);
        $result = ($this->handler)($cmd);
        self::assertTrue($result->isFailure());
        self::assertSame('Medication not found.', $result->getFailure()->getMessage());
    }

    public function testMedicationNotBelongingToProfileReturnsForbidden(): void
    {
        $profile = new PatientProfile(new ProfileId('prof-1'), new UserId('acc-1'), 'Name', new \DateTimeImmutable('1990-01-01'), 'male', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $this->profileRepo->method('findById')->willReturn($profile);

        $medication = new Medication(new MedicationId('med-1'), new ProfileId('prof-other'), 'Name', '10mg', 'pill', 'instr', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $this->medicationRepo->method('findById')->willReturn($medication);

        $cmd = new CreateScheduleCommand('prof-1', 'acc-1', 'med-1', 'daily', new \DateTimeImmutable(), null, [['hour' => 8, 'minute' => 0]]);
        $result = ($this->handler)($cmd);
        self::assertTrue($result->isFailure());
        self::assertSame('Medication does not belong to this profile.', $result->getFailure()->getMessage());
    }

    public function testIdempotentCreateReturnsExistingSchedule(): void
    {
        $profile = new PatientProfile(new ProfileId('prof-1'), new UserId('acc-1'), 'Name', new \DateTimeImmutable('1990-01-01'), 'male', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $this->profileRepo->method('findById')->willReturn($profile);

        $medication = new Medication(new MedicationId('med-1'), new ProfileId('prof-1'), 'Name', '10mg', 'pill', 'instr', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $this->medicationRepo->method('findById')->willReturn($medication);

        $existing = new DailySchedule(new ScheduleId('sched-1'), new MedicationId('med-1'), [new TimeOfDay(8, 0)], new \DateTimeImmutable(), null, 'client-id-1', new \DateTimeImmutable(), new \DateTimeImmutable());
        $this->scheduleRepo->method('findByClientId')->with('client-id-1')->willReturn($existing);

        $cmd = new CreateScheduleCommand('prof-1', 'acc-1', 'med-1', 'daily', new \DateTimeImmutable(), null, [['hour' => 8, 'minute' => 0]], null, null, null, null, 'client-id-1');
        $result = ($this->handler)($cmd);
        self::assertTrue($result->isSuccess());
        /** @var array<string, mixed> $schedData */
        $schedData = $result->getValue();
        self::assertSame('sched-1', $schedData['id']);
    }

    public function testCreateDailyScheduleSuccess(): void
    {
        $profile = new PatientProfile(new ProfileId('prof-1'), new UserId('acc-1'), 'Name', new \DateTimeImmutable('1990-01-01'), 'male', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $this->profileRepo->method('findById')->willReturn($profile);

        $medication = new Medication(new MedicationId('med-1'), new ProfileId('prof-1'), 'Name', '10mg', 'pill', 'instr', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $this->medicationRepo->method('findById')->willReturn($medication);

        $this->scheduleRepo->expects(self::once())->method('save');
        $this->eventBus->expects(self::once())->method('publish')->with(self::isInstanceOf(ScheduleCreatedEvent::class));

        $cmd = new CreateScheduleCommand('prof-1', 'acc-1', 'med-1', 'daily', new \DateTimeImmutable(), null, [['hour' => 8, 'minute' => 0]]);
        $result = ($this->handler)($cmd);

        self::assertTrue($result->isSuccess());
        /** @var array<string, mixed> $schedData */
        $schedData = $result->getValue();
        self::assertSame('daily', $schedData['type']);
    }

    public function testCreateDailyIntervalScheduleSuccess(): void
    {
        $profile = new PatientProfile(new ProfileId('prof-1'), new UserId('acc-1'), 'Name', new \DateTimeImmutable('1990-01-01'), 'male', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $this->profileRepo->method('findById')->willReturn($profile);

        $medication = new Medication(new MedicationId('med-1'), new ProfileId('prof-1'), 'Name', '10mg', 'pill', 'instr', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $this->medicationRepo->method('findById')->willReturn($medication);

        $this->scheduleRepo->expects(self::once())->method('save');
        $this->eventBus->expects(self::once())->method('publish');

        $cmd = new CreateScheduleCommand(
            'prof-1',
            'acc-1',
            'med-1',
            'daily_interval',
            new \DateTimeImmutable(),
            null,
            null,
            4,
            ['hour' => 8, 'minute' => 0],
            ['hour' => 20, 'minute' => 0]
        );
        $result = ($this->handler)($cmd);

        self::assertTrue($result->isSuccess());
        /** @var array<string, mixed> $schedData */
        $schedData = $result->getValue();
        self::assertSame('daily_interval', $schedData['type']);
        self::assertSame(4, $schedData['everyHours']);
    }

    public function testCreateSpecificDaysScheduleSuccess(): void
    {
        $profile = new PatientProfile(new ProfileId('prof-1'), new UserId('acc-1'), 'Name', new \DateTimeImmutable('1990-01-01'), 'male', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $this->profileRepo->method('findById')->willReturn($profile);

        $medication = new Medication(new MedicationId('med-1'), new ProfileId('prof-1'), 'Name', '10mg', 'pill', 'instr', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $this->medicationRepo->method('findById')->willReturn($medication);

        $this->scheduleRepo->expects(self::once())->method('save');
        $this->eventBus->expects(self::once())->method('publish');

        $cmd = new CreateScheduleCommand(
            'prof-1',
            'acc-1',
            'med-1',
            'specific_days',
            new \DateTimeImmutable(),
            null,
            [['hour' => 9, 'minute' => 0]],
            null,
            null,
            null,
            [1, 3, 5]
        );
        $result = ($this->handler)($cmd);

        self::assertTrue($result->isSuccess());
        /** @var array<string, mixed> $schedData */
        $schedData = $result->getValue();
        self::assertSame('specific_days', $schedData['type']);
        self::assertSame([1, 3, 5], $schedData['daysOfWeek']);
    }

    public function testCreateDailyScheduleAnchorsDatesToProfileTimezone(): void
    {
        $profile = new PatientProfile(
            new ProfileId('prof-1'),
            new UserId('acc-1'),
            'Name',
            new \DateTimeImmutable('1990-01-01'),
            'male',
            null,
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
            'America/El_Salvador'
        );
        $this->profileRepo->method('findById')->willReturn($profile);

        $medication = new Medication(new MedicationId('med-1'), new ProfileId('prof-1'), 'Name', '10mg', 'pill', 'instr', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $this->medicationRepo->method('findById')->willReturn($medication);

        $this->scheduleRepo->expects(self::once())->method('save')->with(self::callback(
            static function (Schedule $schedule): bool {
                $tz = new \DateTimeZone('America/El_Salvador');
                $start = $schedule->startDate()->setTimezone($tz);
                $end = $schedule->endDate()?->setTimezone($tz);
                self::assertSame('2026-08-28 00:00:00', $start->format('Y-m-d H:i:s'));
                self::assertNotNull($end);
                self::assertSame('2026-08-30 23:59:59', $end->format('Y-m-d H:i:s'));

                return true;
            }
        ));
        $this->eventBus->expects(self::once())->method('publish');

        $cmd = new CreateScheduleCommand(
            'prof-1',
            'acc-1',
            'med-1',
            'daily',
            new \DateTimeImmutable('2026-08-28'),
            new \DateTimeImmutable('2026-08-30'),
            [['hour' => 16, 'minute' => 25]]
        );
        $result = ($this->handler)($cmd);

        self::assertTrue($result->isSuccess());
    }

    public function testCreateInvalidTypeReturnsValidationFailure(): void
    {
        $profile = new PatientProfile(new ProfileId('prof-1'), new UserId('acc-1'), 'Name', new \DateTimeImmutable('1990-01-01'), 'male', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $this->profileRepo->method('findById')->willReturn($profile);

        $medication = new Medication(new MedicationId('med-1'), new ProfileId('prof-1'), 'Name', '10mg', 'pill', 'instr', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $this->medicationRepo->method('findById')->willReturn($medication);

        $cmd = new CreateScheduleCommand('prof-1', 'acc-1', 'med-1', 'unknown_type', new \DateTimeImmutable());
        $result = ($this->handler)($cmd);

        self::assertTrue($result->isFailure());
        self::assertSame('Invalid schedule type.', $result->getFailure()->getMessage());
    }
}
