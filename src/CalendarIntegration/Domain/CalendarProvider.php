<?php

declare(strict_types=1);

namespace CalendarIntegration\Domain;

interface CalendarProvider extends CalendarGateway, CalendarOAuthClient
{
    public function refreshAccessToken(string $refreshToken): CalendarOAuthTokens;
}
