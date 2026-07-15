<?php

declare(strict_types=1);

namespace Notification\Application\Command;

final class UpdatePreferencesCommand
{
    public function __construct(
        public readonly string $accountId,
        public readonly bool $doseRemindersEnabled,
        public readonly bool $missedDoseNudgesEnabled,
        public readonly bool $refillAlertsEnabled,
        public readonly bool $weeklyStreakSummariesEnabled
    ) {
    }
}
