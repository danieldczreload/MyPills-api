<?php

declare(strict_types=1);

namespace App\Tests\Schedule\Application;

use Medication\Domain\Medication;
use Medication\Domain\MedicationRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Profile\Domain\PatientProfile;
use Profile\Domain\ProfileRepository;
use Schedule\Application\Command\DeleteScheduleCommand;
use Schedule\Application\Command\DeleteScheduleHandler;
use Schedule\Domain\DailySchedule;
use Schedule\Domain\ScheduleDeletedEvent;
use Schedule\Domain\ScheduleRepository;
use Schedule\Domain\ValueObject\TimeOfDay;
use Shared\Application\Bus\EventBus;
use Shared\Domain\ValueObject\MedicationId;
use Shared\Domain\ValueObject\ProfileId;
use Shared\Domain\ValueObject\ScheduleId;
use Shared\Domain\ValueObject\UserId;

final class DeleteScheduleHandlerTest extends TestCase
{
    private ScheduleRepository&MockObject $scheduleRepo;
    private MedicationRepository&MockObject $medicationRepo;
    private ProfileRepository&MockObject $profileRepo;
    private EventBus&MockObject $eventBus;
    private DeleteScheduleHandler $handler;

    protected function setUp(): void
    {
        $this->scheduleRepo = $this->createMock(ScheduleRepository::class);
        $this->medicationRepo = $this->createMock(MedicationRepository::class);
        $this->profileRepo = $this->createMock(ProfileRepository::class);
        $this->eventBus = $this->createMock(EventBus::class);

        $this->handler = new DeleteScheduleHandler(
            $this->scheduleRepo,
            $this->medicationRepo,
            $this->profileRepo,
            $this->eventBus
        );
    }

    public function testDeleteProfileNotFound(): void
    {
        $this->profileRepo->method('findById')->willReturn(null);
        $cmd = new DeleteScheduleCommand('sched-1', 'prof-1', 'acc-1');

        $result = ($this->handler)($cmd);
        self::assertTrue($result->isFailure());
        self::assertSame('Profile not found.', $result->getFailure()->getMessage());
    }

    public function testDeleteScheduleSuccess(): void
    {
        $profile = new PatientProfile(new ProfileId('prof-1'), new UserId('acc-1'), 'Name', new \DateTimeImmutable('1990-01-01'), 'male', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $this->profileRepo->method('findById')->willReturn($profile);

        $medication = new Medication(new MedicationId('med-1'), new ProfileId('prof-1'), 'Name', '10mg', 'pill', 'instr', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $this->medicationRepo->method('findById')->willReturn($medication);

        $schedule = new DailySchedule(new ScheduleId('sched-1'), new MedicationId('med-1'), [new TimeOfDay(8, 0)], new \DateTimeImmutable(), null, null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $this->scheduleRepo->method('findById')->willReturn($schedule);
        $this->scheduleRepo->expects(self::once())->method('delete')->with($schedule);
        $this->eventBus->expects(self::once())->method('publish')->with(self::isInstanceOf(ScheduleDeletedEvent::class));

        $cmd = new DeleteScheduleCommand('sched-1', 'prof-1', 'acc-1');
        $result = ($this->handler)($cmd);

        self::assertTrue($result->isSuccess());
    }
}
