<?php

declare(strict_types=1);

namespace Notification\Application\Command;

final class SendDoseReminderCommand
{
    public function __construct(
        public readonly string $doseEventId,
        public readonly string $accountId,
        public readonly string $medicationName,
        public readonly string $dosage,
        public readonly \DateTimeImmutable $scheduledAt,
        public readonly int $reminderMinutesBefore,
        public readonly bool $doseRemindersEnabled,
        public readonly bool $inAppBannersEnabled
    ) {
    }
}
