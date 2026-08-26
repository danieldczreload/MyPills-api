<?php

declare(strict_types=1);

namespace CalendarIntegration\Domain;

/**
 * Providers able to exchange an OAuth server auth code issued by a native
 * SDK (e.g. google_sign_in on Android) without a browser redirect flow.
 */
interface ServerAuthCodeExchanger
{
    public function exchangeServerAuthCode(string $serverAuthCode): CalendarOAuthTokens;
}
