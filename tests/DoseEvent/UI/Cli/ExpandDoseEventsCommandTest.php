<?php

declare(strict_types=1);

namespace App\Tests\DoseEvent\UI\Cli;

use DoseEvent\Application\Command\ExpandDoseEventsCommand as ExpandDoseEvents;
use DoseEvent\UI\Cli\ExpandDoseEventsCommand;
use PHPUnit\Framework\TestCase;
use Shared\Application\Bus\CommandBus;
use Shared\Domain\Failure;
use Shared\Domain\Result;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ExpandDoseEventsCommandTest extends TestCase
{
    public function testExecuteSuccess(): void
    {
        $bus = $this->createMock(CommandBus::class);
        $bus->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(ExpandDoseEvents::class))
            ->willReturn(Result::success([
                'schedulesScanned' => 2,
                'doseEventsCreated' => 5,
                'profilesQueuedForCalendarSync' => 1,
            ]));

        $cmd = new ExpandDoseEventsCommand($bus);
        $tester = new CommandTester($cmd);

        $status = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $status);
        self::assertStringContainsString('Found 2 schedules.', $tester->getDisplay());
        self::assertStringContainsString('Created 5 dose event(s); queued calendar sync for 1 profile(s).', $tester->getDisplay());
        self::assertStringContainsString('DoseEvent expansion complete.', $tester->getDisplay());
    }

    public function testExecuteFailure(): void
    {
        $bus = $this->createMock(CommandBus::class);
        $bus->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(ExpandDoseEvents::class))
            ->willReturn(Result::failure(Failure::server('DB connection error')));

        $cmd = new ExpandDoseEventsCommand($bus);
        $tester = new CommandTester($cmd);

        $status = $tester->execute([]);

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('Failed to expand dose events: DB connection error', $tester->getDisplay());
    }
}
