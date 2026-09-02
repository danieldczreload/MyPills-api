<?php

declare(strict_types=1);

namespace App\Tests\DoseEvent\Application;

use DoseEvent\Application\Command\TrackDoseCommand;
use DoseEvent\Application\Command\TrackDoseHandler;
use DoseEvent\Domain\DoseEvent;
use DoseEvent\Domain\DoseEventRepository;
use Medication\Domain\Medication;
use Medication\Domain\MedicationRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Profile\Domain\PatientProfile;
use Profile\Domain\ProfileRepository;
use Schedule\Domain\DailySchedule;
use Schedule\Domain\ScheduleRepository;
use Schedule\Domain\ValueObject\TimeOfDay;
use Shared\Domain\ValueObject\DoseEventId;
use Shared\Domain\ValueObject\MedicationId;
use Shared\Domain\ValueObject\ProfileId;
use Shared\Domain\ValueObject\ScheduleId;
use Shared\Domain\ValueObject\UserId;

final class TrackDoseHandlerTest extends TestCase
{
    private DoseEventRepository&MockObject $doseEventRepo;
    private ScheduleRepository&MockObject $scheduleRepo;
    private MedicationRepository&MockObject $medicationRepo;
    private ProfileRepository&MockObject $profileRepo;
    private TrackDoseHandler $handler;

    protected function setUp(): void
    {
        $this->doseEventRepo = $this->createMock(DoseEventRepository::class);
        $this->scheduleRepo = $this->createMock(ScheduleRepository::class);
        $this->medicationRepo = $this->createMock(MedicationRepository::class);
        $this->profileRepo = $this->createMock(ProfileRepository::class);

        $this->handler = new TrackDoseHandler(
            $this->doseEventRepo,
            $this->scheduleRepo,
            $this->medicationRepo,
            $this->profileRepo
        );
    }

    public function testProfileNotFoundReturnsFailure(): void
    {
        $this->profileRepo->method('findById')->willReturn(null);

        $cmd = new TrackDoseCommand(
            'profile-1',
            'account-1',
            'schedule-1',
            new \DateTimeImmutable(),
            'taken'
        );

        $result = ($this->handler)($cmd);
        self::assertTrue($result->isFailure());
        self::assertSame('Profile not found.', $result->getFailure()->getMessage());
    }

    public function testProfileForbiddenWhenNotOwned(): void
    {
        $profile = new PatientProfile(
            new ProfileId('profile-1'),
            new UserId('account-other'),
            'John Doe',
            new \DateTimeImmutable('1990-01-01'),
            'male',
            null,
            new \DateTimeImmutable(),
            new \DateTimeImmutable()
        );
        $this->profileRepo->method('findById')->willReturn($profile);

        $cmd = new TrackDoseCommand(
            'profile-1',
            'account-1',
            'schedule-1',
            new \DateTimeImmutable(),
            'taken'
        );

        $result = ($this->handler)($cmd);
        self::assertTrue($result->isFailure());
        self::assertSame('You do not own this profile.', $result->getFailure()->getMessage());
    }

    public function testScheduleNotFoundReturnsFailure(): void
    {
        $profile = new PatientProfile(
            new ProfileId('profile-1'),
            new UserId('account-1'),
            'John Doe',
            new \DateTimeImmutable('1990-01-01'),
            'male',
            null,
            new \DateTimeImmutable(),
            new \DateTimeImmutable()
        );
        $this->profileRepo->method('findById')->willReturn($profile);
        $this->scheduleRepo->method('findById')->willReturn(null);

        $cmd = new TrackDoseCommand(
            'profile-1',
            'account-1',
            'schedule-1',
            new \DateTimeImmutable(),
            'taken'
        );

        $result = ($this->handler)($cmd);
        self::assertTrue($result->isFailure());
        self::assertSame('Schedule not found.', $result->getFailure()->getMessage());
    }

    public function testScheduleNotBelongingToProfileReturnsForbidden(): void
    {
        $profile = new PatientProfile(
            new ProfileId('profile-1'),
            new UserId('account-1'),
            'John Doe',
            new \DateTimeImmutable('1990-01-01'),
            'male',
            null,
            new \DateTimeImmutable(),
            new \DateTimeImmutable()
        );
        $this->profileRepo->method('findById')->willReturn($profile);

        $medId = MedicationId::generate();
        $schedule = new DailySchedule(
            new ScheduleId('schedule-1'),
            $medId,
            [new TimeOfDay(8, 0)],
            new \DateTimeImmutable(),
            null,
            null,
            new \DateTimeImmutable(),
            new \DateTimeImmutable()
        );
        $this->scheduleRepo->method('findById')->willReturn($schedule);

        $medication = new Medication(
            $medId,
            new ProfileId('profile-other'),
            'Aspirin',
            'pill',
            'instructions',
            null,
            new \DateTimeImmutable(),
            new \DateTimeImmutable()
        );
        $this->medicationRepo->method('findById')->willReturn($medication);

        $cmd = new TrackDoseCommand(
            'profile-1',
            'account-1',
            'schedule-1',
            new \DateTimeImmutable(),
            'taken'
        );

        $result = ($this->handler)($cmd);
        self::assertTrue($result->isFailure());
        self::assertSame('Schedule does not belong to this profile.', $result->getFailure()->getMessage());
    }

    public function testInvalidStatusReturnsValidationFailure(): void
    {
        $profile = new PatientProfile(
            new ProfileId('profile-1'),
            new UserId('account-1'),
            'John Doe',
            new \DateTimeImmutable('1990-01-01'),
            'male',
            null,
            new \DateTimeImmutable(),
            new \DateTimeImmutable()
        );
        $this->profileRepo->method('findById')->willReturn($profile);

        $medId = MedicationId::generate();
        $schedule = new DailySchedule(
            new ScheduleId('schedule-1'),
            $medId,
            [new TimeOfDay(8, 0)],
            new \DateTimeImmutable(),
            null,
            null,
            new \DateTimeImmutable(),
            new \DateTimeImmutable()
        );
        $this->scheduleRepo->method('findById')->willReturn($schedule);

        $medication = new Medication(
            $medId,
            new ProfileId('profile-1'),
            'Aspirin',
            'pill',
            'instructions',
            null,
            new \DateTimeImmutable(),
            new \DateTimeImmutable()
        );
        $this->medicationRepo->method('findById')->willReturn($medication);

        $cmd = new TrackDoseCommand(
            'profile-1',
            'account-1',
            'schedule-1',
            new \DateTimeImmutable(),
            'invalid_status'
        );

        $result = ($this->handler)($cmd);
        self::assertTrue($result->isFailure());
        self::assertSame('Invalid status.', $result->getFailure()->getMessage());
    }

    public function testTrackNewDoseSuccessfully(): void
    {
        $profile = new PatientProfile(
            new ProfileId('profile-1'),
            new UserId('account-1'),
            'John Doe',
            new \DateTimeImmutable('1990-01-01'),
            'male',
            null,
            new \DateTimeImmutable(),
            new \DateTimeImmutable()
        );
        $this->profileRepo->method('findById')->willReturn($profile);

        $medId = MedicationId::generate();
        $schedule = new DailySchedule(
            new ScheduleId('schedule-1'),
            $medId,
            [new TimeOfDay(8, 0)],
            new \DateTimeImmutable(),
            null,
            null,
            new \DateTimeImmutable(),
            new \DateTimeImmutable()
        );
        $this->scheduleRepo->method('findById')->willReturn($schedule);

        $medication = new Medication(
            $medId,
            new ProfileId('profile-1'),
            'Aspirin',
            'pill',
            'instructions',
            null,
            new \DateTimeImmutable(),
            new \DateTimeImmutable()
        );
        $this->medicationRepo->method('findById')->willReturn($medication);
        $this->doseEventRepo->method('findByClientId')->willReturn(null);
        $this->doseEventRepo->method('findByScheduleIdsAndRange')->willReturn([]);
        $this->doseEventRepo->expects(self::once())->method('save');

        $scheduledAt = new \DateTimeImmutable('2026-08-01 08:00:00');
        $cmd = new TrackDoseCommand(
            'profile-1',
            'account-1',
            'schedule-1',
            $scheduledAt,
            'taken',
            null,
            'client-dose-1'
        );

        $result = ($this->handler)($cmd);
        self::assertTrue($result->isSuccess());
        /** @var array<string, mixed> $data */
        $data = $result->getValue();
        self::assertSame('taken', $data['status']);
        self::assertSame('client-dose-1', $data['clientId']);
    }

    public function testTrackExistingDoseSuccessfully(): void
    {
        $profile = new PatientProfile(
            new ProfileId('profile-1'),
            new UserId('account-1'),
            'John Doe',
            new \DateTimeImmutable('1990-01-01'),
            'male',
            null,
            new \DateTimeImmutable(),
            new \DateTimeImmutable()
        );
        $this->profileRepo->method('findById')->willReturn($profile);

        $medId = MedicationId::generate();
        $schedule = new DailySchedule(
            new ScheduleId('schedule-1'),
            $medId,
            [new TimeOfDay(8, 0)],
            new \DateTimeImmutable(),
            null,
            null,
            new \DateTimeImmutable(),
            new \DateTimeImmutable()
        );
        $this->scheduleRepo->method('findById')->willReturn($schedule);

        $medication = new Medication(
            $medId,
            new ProfileId('profile-1'),
            'Aspirin',
            'pill',
            'instructions',
            null,
            new \DateTimeImmutable(),
            new \DateTimeImmutable()
        );
        $this->medicationRepo->method('findById')->willReturn($medication);

        $existingEvent = DoseEvent::create(
            DoseEventId::generate(),
            $medId,
            new ScheduleId('schedule-1'),
            new \DateTimeImmutable('2026-08-01 08:00:00')
        );

        $this->doseEventRepo->method('findByClientId')->willReturn($existingEvent);
        $this->doseEventRepo->expects(self::once())->method('save');

        $cmd = new TrackDoseCommand(
            'profile-1',
            'account-1',
            'schedule-1',
            new \DateTimeImmutable('2026-08-01 08:00:00'),
            'skipped',
            null,
            'client-existing-1'
        );

        $result = ($this->handler)($cmd);
        self::assertTrue($result->isSuccess());
        /** @var array<string, mixed> $data */
        $data = $result->getValue();
        self::assertSame('skipped', $data['status']);
    }
}
