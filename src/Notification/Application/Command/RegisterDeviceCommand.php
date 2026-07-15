<?php

declare(strict_types=1);

namespace Notification\Application\Command;

final class RegisterDeviceCommand
{
    public function __construct(
        public readonly string $accountId,
        public readonly string $fcmToken,
        public readonly string $platform,
        public readonly string $locale
    ) {
    }
}
