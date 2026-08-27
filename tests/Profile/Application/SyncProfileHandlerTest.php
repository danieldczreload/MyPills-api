<?php

declare(strict_types=1);

namespace App\Tests\Profile\Application;

use DoseEvent\Domain\DoseEvent;
use DoseEvent\Domain\DoseEventRepository;
use Medication\Domain\Medication;
use Medication\Domain\MedicationRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Profile\Application\Query\SyncProfileHandler;
use Profile\Application\Query\SyncProfileQuery;
use Profile\Domain\PatientProfile;
use Profile\Domain\ProfileRepository;
use Profile\Domain\Tombstone;
use Profile\Domain\TombstoneRepository;
use Schedule\Domain\DailySchedule;
use Schedule\Domain\ScheduleRepository;
use Schedule\Domain\ValueObject\TimeOfDay;
use Shared\Domain\ValueObject\DoseEventId;
use Shared\Domain\ValueObject\MedicationId;
use Shared\Domain\ValueObject\ProfileId;
use Shared\Domain\ValueObject\ScheduleId;
use Shared\Domain\ValueObject\UserId;

final class SyncProfileHandlerTest extends TestCase
{
    private ProfileRepository&MockObject $profileRepo;
    private MedicationRepository&MockObject $medicationRepo;
    private ScheduleRepository&MockObject $scheduleRepo;
    private DoseEventRepository&MockObject $doseEventRepo;
    private TombstoneRepository&MockObject $tombstoneRepo;
    private \Taxonomy\Domain\TaxonomyGroupRepository&MockObject $taxonomyRepo;
    private SyncProfileHandler $handler;

    protected function setUp(): void
    {
        $this->profileRepo = $this->createMock(ProfileRepository::class);
        $this->medicationRepo = $this->createMock(MedicationRepository::class);
        $this->scheduleRepo = $this->createMock(ScheduleRepository::class);
        $this->doseEventRepo = $this->createMock(DoseEventRepository::class);
        $this->tombstoneRepo = $this->createMock(TombstoneRepository::class);
        $this->taxonomyRepo = $this->createMock(\Taxonomy\Domain\TaxonomyGroupRepository::class);

        $this->handler = new SyncProfileHandler(
            $this->profileRepo,
            $this->medicationRepo,
            $this->scheduleRepo,
            $this->doseEventRepo,
            $this->tombstoneRepo,
            $this->taxonomyRepo
        );
    }

    public function testProfileNotFoundReturnsFailure(): void
    {
        $this->profileRepo->method('findById')->willReturn(null);
        $query = new SyncProfileQuery('prof-1', 'acc-1', new \DateTimeImmutable('2026-08-01'));

        $result = ($this->handler)($query);
        self::assertTrue($result->isFailure());
        self::assertSame('Profile not found.', $result->getFailure()->getMessage());
    }

    public function testProfileForbiddenReturnsFailure(): void
    {
        $profile = new PatientProfile(new ProfileId('prof-1'), new UserId('acc-other'), 'John Doe', new \DateTimeImmutable('1990-01-01'), 'male', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $this->profileRepo->method('findById')->willReturn($profile);
        $query = new SyncProfileQuery('prof-1', 'acc-1', new \DateTimeImmutable('2026-08-01'));

        $result = ($this->handler)($query);
        self::assertTrue($result->isFailure());
        self::assertSame('You do not own this profile.', $result->getFailure()->getMessage());
    }

    public function testSyncReturnsEntitiesAndTombstones(): void
    {
        $since = new \DateTimeImmutable('2026-08-01 00:00:00');
        $profile = new PatientProfile(new ProfileId('prof-1'), new UserId('acc-1'), 'John Doe', new \DateTimeImmutable('1990-01-01'), 'male', null, $since, $since);
        $this->profileRepo->method('findById')->willReturn($profile);

        $medId = new MedicationId('med-1');
        $medication = new Medication($medId, new ProfileId('prof-1'), 'Aspirin', '100mg', 'pill', 'take once', null, $since, $since);
        $this->medicationRepo->method('findByProfileId')->willReturn([$medication]);

        $schedule = new DailySchedule(new ScheduleId('sch-1'), $medId, [new TimeOfDay(8, 0)], $since, null, null, $since, $since);
        $this->scheduleRepo->method('findByMedicationIds')->willReturn([$schedule]);

        $doseEvent = DoseEvent::create(new DoseEventId('dose-1'), $medId, new ScheduleId('sch-1'), $since);
        $this->doseEventRepo->method('findByScheduleIdsAndRange')->willReturn([$doseEvent]);

        $tombstone = Tombstone::create(new ProfileId('prof-1'), 'medication', 'med-old');
        $this->tombstoneRepo->method('findByProfileIdSince')->willReturn([$tombstone]);

        $taxGroup = \Taxonomy\Domain\TaxonomyGroup::create(new \Shared\Domain\ValueObject\TaxonomyGroupId('tax-1'), new ProfileId('prof-1'), 'category', 'Cardio', null, null, null, true, 'client-tax');
        $this->taxonomyRepo->method('findByProfileId')->willReturn([$taxGroup]);

        $query = new SyncProfileQuery('prof-1', 'acc-1', $since);
        $result = ($this->handler)($query);

        self::assertTrue($result->isSuccess());
        $data = $result->getValue();
        self::assertCount(1, $data['medications']);
        self::assertCount(1, $data['schedules']);
        self::assertCount(1, $data['doseEvents']);
        self::assertCount(1, $data['taxonomyGroups']);
        self::assertCount(1, $data['tombstones']);
        self::assertSame('med-old', $data['tombstones'][0]['id']);
        self::assertSame('tax-1', $data['taxonomyGroups'][0]['id']);
    }

    public function testSyncWithIntervalAndSpecificDaysSchedules(): void
    {
        $since = new \DateTimeImmutable('2026-08-01 00:00:00');
        $profile = new PatientProfile(new ProfileId('prof-1'), new UserId('acc-1'), 'John Doe', new \DateTimeImmutable('1990-01-01'), 'male', null, $since, $since);
        $this->profileRepo->method('findById')->willReturn($profile);

        $medId = new MedicationId('med-1');
        $medication = new Medication($medId, new ProfileId('prof-1'), 'Aspirin', '100mg', 'pill', 'take once', null, $since, $since);
        $this->medicationRepo->method('findByProfileId')->willReturn([$medication]);

        $intervalSched = new \Schedule\Domain\DailyIntervalSchedule(new ScheduleId('sch-int'), $medId, 6, new TimeOfDay(6, 0), new TimeOfDay(22, 0), $since, null, null, $since, $since);
        $specSched = new \Schedule\Domain\SpecificDaysSchedule(new ScheduleId('sch-spec'), $medId, [1, 3, 5], [new TimeOfDay(9, 0)], $since, null, null, $since, $since);
        $this->scheduleRepo->method('findByMedicationIds')->willReturn([$intervalSched, $specSched]);

        $this->doseEventRepo->method('findByScheduleIdsAndRange')->willReturn([]);
        $this->tombstoneRepo->method('findByProfileIdSince')->willReturn([]);
        $this->taxonomyRepo->method('findByProfileId')->willReturn([]);

        $query = new SyncProfileQuery('prof-1', 'acc-1', $since);
        $result = ($this->handler)($query);

        self::assertTrue($result->isSuccess());
        $data = $result->getValue();
        self::assertCount(2, $data['schedules']);
        self::assertSame(6, $data['schedules'][0]['everyHours']);
        self::assertSame([1, 3, 5], $data['schedules'][1]['daysOfWeek']);
    }
}
