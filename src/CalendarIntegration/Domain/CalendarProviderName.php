<?php

declare(strict_types=1);

namespace CalendarIntegration\Domain;

enum CalendarProviderName: string
{
    case GOOGLE = 'google';
    case MICROSOFT = 'microsoft';

    public static function isSupported(string $provider): bool
    {
        return self::tryFrom($provider) !== null;
    }
}
