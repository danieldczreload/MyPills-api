<?php

declare(strict_types=1);

namespace Notification\Application\Command;

final readonly class CancelRecurringNotificationsCommand
{
    public function __construct(
        public string $profileId,
        public string $accountId,
        public ?string $scheduleId = null,
        public ?string $medicationId = null,
        public bool $cancelPush = true,
        public bool $cancelCalendar = true,
        public bool $deleteSchedule = false
    ) {
    }
}
