<?php

declare(strict_types=1);

namespace Notification\Application\Command;

final readonly class CancelNotificationCommand
{
    public function __construct(
        public string $profileId,
        public string $accountId,
        public string $doseEventId,
        public bool $cancelPush = true,
        public bool $cancelCalendar = true,
        public ?bool $cancelAlarm = null
    ) {
    }

    public function shouldCancelAlarm(): bool
    {
        return $this->cancelAlarm ?? $this->cancelPush;
    }
}
