<?php

declare(strict_types=1);

namespace Notification\Domain;

use Shared\Domain\ValueObject\DoseEventId;
use Shared\Domain\ValueObject\UserId;

final class DueDoseReminder
{
    public function __construct(
        public readonly DoseEventId $doseEventId,
        public readonly UserId $accountId,
        public readonly string $medicationName,
        public readonly string $dosage,
        public readonly \DateTimeImmutable $scheduledAt,
        public readonly int $reminderMinutesBefore,
        public readonly bool $doseRemindersEnabled,
        public readonly bool $inAppBannersEnabled
    ) {
    }
}
