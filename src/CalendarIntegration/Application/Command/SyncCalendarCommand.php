<?php

declare(strict_types=1);

namespace CalendarIntegration\Application\Command;

final class SyncCalendarCommand
{
    public function __construct(
        public readonly string $accountId,
        public readonly ?string $profileId = null
    ) {
    }
}
