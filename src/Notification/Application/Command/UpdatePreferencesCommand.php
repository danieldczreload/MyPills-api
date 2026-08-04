<?php

declare(strict_types=1);

namespace Notification\Application\Command;

final class UpdatePreferencesCommand
{
    public function __construct(
        public readonly string $accountId,
        public readonly ?bool $doseRemindersEnabled = null,
        public readonly ?bool $missedDoseNudgesEnabled = null,
        public readonly ?bool $refillAlertsEnabled = null,
        public readonly ?bool $weeklyStreakSummariesEnabled = null
    ) {
    }
}
