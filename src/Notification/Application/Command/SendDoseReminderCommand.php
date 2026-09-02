<?php

declare(strict_types=1);

namespace Notification\Application\Command;

use Shared\Domain\ValueObject\Dose;

final class SendDoseReminderCommand
{
    public function __construct(
        public readonly string $doseEventId,
        public readonly string $accountId,
        public readonly string $medicationName,
        public readonly ?Dose $dose,
        public readonly \DateTimeImmutable $scheduledAt,
        public readonly int $reminderMinutesBefore,
        public readonly bool $doseRemindersEnabled,
        public readonly bool $inAppBannersEnabled
    ) {
    }
}
