<?php

declare(strict_types=1);

namespace CalendarIntegration\Application\Command;

final class CompleteCalendarAuthorizationCommand
{
    public function __construct(
        public readonly string $profileId,
        public readonly string $accountId,
        public readonly string $provider,
        public readonly string $code,
        public readonly string $state,
        public readonly string $codeVerifier
    ) {
    }
}
