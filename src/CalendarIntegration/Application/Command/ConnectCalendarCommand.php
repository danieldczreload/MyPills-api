<?php

declare(strict_types=1);

namespace CalendarIntegration\Application\Command;

final class ConnectCalendarCommand
{
    public function __construct(
        public readonly string $profileId,
        public readonly string $accountId,
        public readonly string $provider,
        public readonly string $refreshToken
    ) {
    }
}
