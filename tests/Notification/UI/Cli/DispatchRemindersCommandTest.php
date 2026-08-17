<?php

declare(strict_types=1);

namespace App\Tests\Notification\UI\Cli;

use Notification\Application\Command\DispatchDueRemindersCommand;
use Notification\UI\Cli\DispatchRemindersCommand;
use PHPUnit\Framework\TestCase;
use Shared\Application\Bus\CommandBus;
use Shared\Domain\Failure;
use Shared\Domain\Result;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class DispatchRemindersCommandTest extends TestCase
{
    public function testExecuteSuccess(): void
    {
        $bus = $this->createMock(CommandBus::class);
        $bus->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(DispatchDueRemindersCommand::class))
            ->willReturn(Result::success(['dispatched' => 3]));

        $cmd = new DispatchRemindersCommand($bus);
        $tester = new CommandTester($cmd);

        $status = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $status);
        self::assertStringContainsString('Successfully evaluated and dispatched 3 dose reminder(s).', $tester->getDisplay());
    }

    public function testExecuteFailure(): void
    {
        $bus = $this->createMock(CommandBus::class);
        $bus->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(DispatchDueRemindersCommand::class))
            ->willReturn(Result::failure(Failure::server('DB connection error')));

        $cmd = new DispatchRemindersCommand($bus);
        $tester = new CommandTester($cmd);

        $status = $tester->execute([]);

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('Failed to dispatch reminders: DB connection error', $tester->getDisplay());
    }
}
