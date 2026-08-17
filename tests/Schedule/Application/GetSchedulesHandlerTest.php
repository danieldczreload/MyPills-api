<?php

declare(strict_types=1);

namespace App\Tests\Schedule\Application;

use Medication\Domain\Medication;
use Medication\Domain\MedicationRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Profile\Domain\PatientProfile;
use Profile\Domain\ProfileRepository;
use Schedule\Application\Query\GetSchedulesHandler;
use Schedule\Application\Query\GetSchedulesQuery;
use Schedule\Domain\DailyIntervalSchedule;
use Schedule\Domain\DailySchedule;
use Schedule\Domain\ScheduleRepository;
use Schedule\Domain\SpecificDaysSchedule;
use Schedule\Domain\ValueObject\TimeOfDay;
use Shared\Domain\ValueObject\MedicationId;
use Shared\Domain\ValueObject\ProfileId;
use Shared\Domain\ValueObject\ScheduleId;
use Shared\Domain\ValueObject\UserId;

final class GetSchedulesHandlerTest extends TestCase
{
    private ScheduleRepository&MockObject $scheduleRepo;
    private MedicationRepository&MockObject $medicationRepo;
    private ProfileRepository&MockObject $profileRepo;
    private GetSchedulesHandler $handler;

    protected function setUp(): void
    {
        $this->scheduleRepo = $this->createMock(ScheduleRepository::class);
        $this->medicationRepo = $this->createMock(MedicationRepository::class);
        $this->profileRepo = $this->createMock(ProfileRepository::class);

        $this->handler = new GetSchedulesHandler(
            $this->scheduleRepo,
            $this->medicationRepo,
            $this->profileRepo
        );
    }

    public function testGetSchedulesSuccess(): void
    {
        $profile = new PatientProfile(new ProfileId('prof-1'), new UserId('acc-1'), 'Name', new \DateTimeImmutable('1990-01-01'), 'male', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $this->profileRepo->method('findById')->willReturn($profile);

        $medId = new MedicationId('med-1');
        $medication = new Medication($medId, new ProfileId('prof-1'), 'Name', '10mg', 'pill', 'instr', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $this->medicationRepo->method('findByProfileId')->willReturn([$medication]);

        $daily = new DailySchedule(new ScheduleId('s-1'), $medId, [new TimeOfDay(8, 0)], new \DateTimeImmutable(), null, null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $interval = new DailyIntervalSchedule(new ScheduleId('s-2'), $medId, 4, new TimeOfDay(8, 0), new TimeOfDay(16, 0), new \DateTimeImmutable(), null, null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $specific = new SpecificDaysSchedule(new ScheduleId('s-3'), $medId, [1, 2], [new TimeOfDay(10, 0)], new \DateTimeImmutable(), null, null, new \DateTimeImmutable(), new \DateTimeImmutable());

        $this->scheduleRepo->method('findByMedicationIds')->willReturn([$daily, $interval, $specific]);

        $query = new GetSchedulesQuery('prof-1', 'acc-1');
        $result = ($this->handler)($query);

        self::assertTrue($result->isSuccess());
        /** @var list<array<string, mixed>> $data */
        $data = $result->getValue();
        self::assertCount(3, $data);
        self::assertSame('daily', $data[0]['type']);
        self::assertSame('daily_interval', $data[1]['type']);
        self::assertSame('specific_days', $data[2]['type']);
    }
}
