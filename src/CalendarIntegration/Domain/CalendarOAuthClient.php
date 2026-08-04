<?php

declare(strict_types=1);

namespace CalendarIntegration\Domain;

interface CalendarOAuthClient
{
    public function authorizationUrl(string $state, string $codeChallenge): string;

    public function exchangeAuthorizationCode(string $code, string $codeVerifier): CalendarOAuthTokens;
}
