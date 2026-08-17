<?php

declare(strict_types=1);

namespace App\Tests\Notification\Application;

use Notification\Application\Command\DispatchDueRemindersCommand;
use Notification\Application\Command\DispatchDueRemindersHandler;
use Notification\Application\Command\SendDoseReminderCommand;
use Notification\Domain\DueDoseReminder;
use Notification\Domain\DueDoseReminderRepository;
use PHPUnit\Framework\TestCase;
use Shared\Application\Bus\CommandBus;
use Shared\Domain\Result;
use Shared\Domain\ValueObject\DoseEventId;
use Shared\Domain\ValueObject\UserId;

final class DispatchDueRemindersHandlerTest extends TestCase
{
    public function testDispatchesSendDoseReminderCommandForEachDueReminder(): void
    {
        $now = new \DateTimeImmutable('2026-08-17 12:00:00', new \DateTimeZone('UTC'));
        $reminder1 = new DueDoseReminder(
            new DoseEventId('00000000-0000-0000-0000-000000000001'),
            new UserId('00000000-0000-0000-0000-000000000002'),
            'Paracetamol',
            '500mg',
            $now,
            0,
            true,
            true
        );

        $reminderRepo = $this->createMock(DueDoseReminderRepository::class);
        $reminderRepo->method('findDueDoseReminders')->with($now)->willReturn([$reminder1]);

        $commandBus = $this->createMock(CommandBus::class);
        $commandBus->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(SendDoseReminderCommand::class))
            ->willReturn(Result::success(['sent' => 1]));

        $handler = new DispatchDueRemindersHandler($reminderRepo, $commandBus);
        $result = $handler(new DispatchDueRemindersCommand($now));

        self::assertTrue($result->isSuccess());
        /** @var array{dispatched: int} $data */
        $data = $result->getValue();
        self::assertSame(1, $data['dispatched']);
    }
}
