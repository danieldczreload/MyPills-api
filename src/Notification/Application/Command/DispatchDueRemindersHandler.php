<?php

declare(strict_types=1);

namespace Notification\Application\Command;

use Notification\Domain\DueDoseReminderRepository;
use Shared\Application\Bus\CommandBus;
use Shared\Domain\Result;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class DispatchDueRemindersHandler
{
    public function __construct(
        private readonly DueDoseReminderRepository $dueDoseReminderRepository,
        private readonly CommandBus $commandBus
    ) {
    }

    /**
     * @return Result<array{dispatched: int}>
     */
    public function __invoke(DispatchDueRemindersCommand $command): Result
    {
        $now = $command->referenceTime ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $dueReminders = $this->dueDoseReminderRepository->findDueDoseReminders($now);

        $dispatched = 0;

        foreach ($dueReminders as $reminder) {
            $sendCommand = new SendDoseReminderCommand(
                $reminder->doseEventId->value(),
                $reminder->accountId->value(),
                $reminder->medicationName,
                $reminder->dosage,
                $reminder->scheduledAt,
                $reminder->reminderMinutesBefore,
                $reminder->doseRemindersEnabled,
                $reminder->inAppBannersEnabled
            );

            $this->commandBus->dispatch($sendCommand);
            ++$dispatched;
        }

        return Result::success(['dispatched' => $dispatched]);
    }
}
