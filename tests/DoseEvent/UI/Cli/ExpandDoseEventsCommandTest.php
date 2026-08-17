<?php

declare(strict_types=1);

namespace App\Tests\DoseEvent\UI\Cli;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use DoseEvent\Domain\DoseEventExpander;
use DoseEvent\Domain\DoseEventRepository;
use DoseEvent\UI\Cli\ExpandDoseEventsCommand;
use PHPUnit\Framework\TestCase;
use Schedule\Domain\DailySchedule;
use Schedule\Domain\ScheduleRepository;
use Schedule\Domain\ValueObject\TimeOfDay;
use Schedule\Infrastructure\Persistence\ScheduleDoctrineEntity;
use Shared\Domain\ValueObject\MedicationId;
use Shared\Domain\ValueObject\ScheduleId;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ExpandDoseEventsCommandTest extends TestCase
{
    public function testExecuteCommand(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $scheduleRepo = $this->createMock(ScheduleRepository::class);
        $doseEventRepo = $this->createMock(DoseEventRepository::class);
        $expander = new DoseEventExpander();

        /** @var EntityRepository<ScheduleDoctrineEntity>&\PHPUnit\Framework\MockObject\MockObject $entityRepo */
        $entityRepo = $this->createMock(EntityRepository::class);
        $doctrineEntity = $this->createMock(ScheduleDoctrineEntity::class);
        $doctrineEntity->method('getId')->willReturn('123e4567-e89b-12d3-a456-426614174000');

        $entityRepo->method('findAll')->willReturn([$doctrineEntity]);
        $em->method('getRepository')->with(ScheduleDoctrineEntity::class)->willReturn($entityRepo);

        $schedule = new DailySchedule(
            new ScheduleId('123e4567-e89b-12d3-a456-426614174000'),
            MedicationId::generate(),
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

        $cmd = new ExpandDoseEventsCommand($em, $scheduleRepo, $doseEventRepo, $expander);
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
        $expander = new DoseEventExpander();

        /** @var EntityRepository<ScheduleDoctrineEntity>&\PHPUnit\Framework\MockObject\MockObject $entityRepo */
        $entityRepo = $this->createMock(EntityRepository::class);
        $doctrineEntity = $this->createMock(ScheduleDoctrineEntity::class);
        $doctrineEntity->method('getId')->willReturn('123e4567-e89b-12d3-a456-426614174000');

        $entityRepo->method('findAll')->willReturn([$doctrineEntity]);
        $em->method('getRepository')->with(ScheduleDoctrineEntity::class)->willReturn($entityRepo);

        $scheduleRepo->method('findById')->willReturn(null);

        $cmd = new ExpandDoseEventsCommand($em, $scheduleRepo, $doseEventRepo, $expander);
        $tester = new CommandTester($cmd);

        $status = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $status);
        self::assertStringContainsString('DoseEvent expansion complete.', $tester->getDisplay());
    }
}
