<?php

declare(strict_types=1);

namespace App\Tests\DoseEvent\UI\Cli;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use DoseEvent\Domain\DoseEventExpander;
use DoseEvent\Domain\DoseEventRepository;
use DoseEvent\Domain\DoseEventsExpandedEvent;
use DoseEvent\UI\Cli\ExpandDoseEventsCommand;
use Medication\Domain\Medication;
use Medication\Domain\MedicationRepository;
use PHPUnit\Framework\TestCase;
use Profile\Domain\PatientProfile;
use Profile\Domain\ProfileRepository;
use Schedule\Domain\DailySchedule;
use Schedule\Domain\ScheduleRepository;
use Schedule\Domain\ValueObject\TimeOfDay;
use Schedule\Infrastructure\Persistence\ScheduleDoctrineEntity;
use Shared\Application\Bus\EventBus;
use Shared\Domain\ValueObject\MedicationId;
use Shared\Domain\ValueObject\ProfileId;
use Shared\Domain\ValueObject\ScheduleId;
use Shared\Domain\ValueObject\UserId;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ExpandDoseEventsCommandTest extends TestCase
{
    public function testExecuteCommand(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $scheduleRepo = $this->createMock(ScheduleRepository::class);
        $doseEventRepo = $this->createMock(DoseEventRepository::class);
        $medicationRepo = $this->createMock(MedicationRepository::class);
        $profileRepo = $this->createMock(ProfileRepository::class);
        $eventBus = $this->createMock(EventBus::class);
        $expander = new DoseEventExpander();

        /** @var EntityRepository<ScheduleDoctrineEntity>&\PHPUnit\Framework\MockObject\MockObject $entityRepo */
        $entityRepo = $this->createMock(EntityRepository::class);
        $doctrineEntity = $this->createMock(ScheduleDoctrineEntity::class);
        $doctrineEntity->method('getId')->willReturn('123e4567-e89b-12d3-a456-426614174000');

        $entityRepo->method('findAll')->willReturn([$doctrineEntity]);
        $em->method('getRepository')->with(ScheduleDoctrineEntity::class)->willReturn($entityRepo);

        $medicationId = new MedicationId('223e4567-e89b-12d3-a456-426614174000');
        $schedule = new DailySchedule(
            new ScheduleId('123e4567-e89b-12d3-a456-426614174000'),
            $medicationId,
            [new TimeOfDay(8, 0)],
            new \DateTimeImmutable(),
            null,
            null,
            new \DateTimeImmutable(),
            new \DateTimeImmutable()
        );
        $scheduleRepo->method('findById')->willReturn($schedule);
        $doseEventRepo->method('findByScheduleIdsAndRange')->willReturn([]);
        $doseEventRepo->expects(self::atLeastOnce())->method('save');

        $medication = new Medication(
            $medicationId,
            new ProfileId('prof-1'),
            'Aspirin',
            '100mg',
            null,
            null,
            null,
            new \DateTimeImmutable(),
            new \DateTimeImmutable()
        );
        $medicationRepo->method('findById')->willReturn($medication);

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
        $profileRepo->method('findById')->willReturn($profile);

        $eventBus->expects(self::once())->method('publish')->with(self::callback(
            static function (object $event): bool {
                return $event instanceof DoseEventsExpandedEvent
                    && $event->profileId === 'prof-1'
                    && $event->scheduleId === '123e4567-e89b-12d3-a456-426614174000';
            }
        ));

        $cmd = new ExpandDoseEventsCommand($em, $scheduleRepo, $doseEventRepo, $expander, $medicationRepo, $profileRepo, $eventBus);
        $tester = new CommandTester($cmd);

        $status = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $status);
        self::assertStringContainsString('DoseEvent expansion complete.', $tester->getDisplay());
    }

    public function testExecuteCommandWhenScheduleNotFound(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $scheduleRepo = $this->createMock(ScheduleRepository::class);
        $doseEventRepo = $this->createMock(DoseEventRepository::class);
        $medicationRepo = $this->createMock(MedicationRepository::class);
        $profileRepo = $this->createMock(ProfileRepository::class);
        $eventBus = $this->createMock(EventBus::class);
        $expander = new DoseEventExpander();

        /** @var EntityRepository<ScheduleDoctrineEntity>&\PHPUnit\Framework\MockObject\MockObject $entityRepo */
        $entityRepo = $this->createMock(EntityRepository::class);
        $doctrineEntity = $this->createMock(ScheduleDoctrineEntity::class);
        $doctrineEntity->method('getId')->willReturn('123e4567-e89b-12d3-a456-426614174000');

        $entityRepo->method('findAll')->willReturn([$doctrineEntity]);
        $em->method('getRepository')->with(ScheduleDoctrineEntity::class)->willReturn($entityRepo);

        $scheduleRepo->method('findById')->willReturn(null);
        $eventBus->expects(self::never())->method('publish');

        $cmd = new ExpandDoseEventsCommand($em, $scheduleRepo, $doseEventRepo, $expander, $medicationRepo, $profileRepo, $eventBus);
        $tester = new CommandTester($cmd);

        $status = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $status);
        self::assertStringContainsString('DoseEvent expansion complete.', $tester->getDisplay());
    }

    public function testPublishesOneEventPerAffectedProfile(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $scheduleRepo = $this->createMock(ScheduleRepository::class);
        $doseEventRepo = $this->createMock(DoseEventRepository::class);
        $medicationRepo = $this->createMock(MedicationRepository::class);
        $profileRepo = $this->createMock(ProfileRepository::class);
        $eventBus = $this->createMock(EventBus::class);
        $expander = new DoseEventExpander();

        /** @var EntityRepository<ScheduleDoctrineEntity>&\PHPUnit\Framework\MockObject\MockObject $entityRepo */
        $entityRepo = $this->createMock(EntityRepository::class);
        $entityA = $this->createMock(ScheduleDoctrineEntity::class);
        $entityA->method('getId')->willReturn('123e4567-e89b-12d3-a456-426614174000');
        $entityB = $this->createMock(ScheduleDoctrineEntity::class);
        $entityB->method('getId')->willReturn('323e4567-e89b-12d3-a456-426614174000');

        $entityRepo->method('findAll')->willReturn([$entityA, $entityB]);
        $em->method('getRepository')->with(ScheduleDoctrineEntity::class)->willReturn($entityRepo);

        $medicationId = new MedicationId('223e4567-e89b-12d3-a456-426614174000');
        $scheduleA = new DailySchedule(
            new ScheduleId('123e4567-e89b-12d3-a456-426614174000'),
            $medicationId,
            [new TimeOfDay(8, 0)],
            new \DateTimeImmutable(),
            null,
            null,
            new \DateTimeImmutable(),
            new \DateTimeImmutable()
        );
        $scheduleB = new DailySchedule(
            new ScheduleId('323e4567-e89b-12d3-a456-426614174000'),
            $medicationId,
            [new TimeOfDay(20, 0)],
            new \DateTimeImmutable(),
            null,
            null,
            new \DateTimeImmutable(),
            new \DateTimeImmutable()
        );
        $scheduleRepo->method('findById')->willReturnOnConsecutiveCalls($scheduleA, $scheduleB);
        $doseEventRepo->method('findByScheduleIdsAndRange')->willReturn([]);
        $doseEventRepo->expects(self::atLeastOnce())->method('save');

        $medication = new Medication(
            $medicationId,
            new ProfileId('prof-1'),
            'Aspirin',
            '100mg',
            null,
            null,
            null,
            new \DateTimeImmutable(),
            new \DateTimeImmutable()
        );
        $medicationRepo->method('findById')->willReturn($medication);
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

        $cmd = new ExpandDoseEventsCommand($em, $scheduleRepo, $doseEventRepo, $expander, $medicationRepo, $profileRepo, $eventBus);
        $tester = new CommandTester($cmd);

        self::assertSame(Command::SUCCESS, $tester->execute([]));
    }
}
