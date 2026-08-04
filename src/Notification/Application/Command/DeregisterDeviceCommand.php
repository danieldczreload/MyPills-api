<?php

declare(strict_types=1);

namespace Notification\Application\Command;

final class DeregisterDeviceCommand
{
    public function __construct(
        public readonly string $deviceId,
        public readonly string $accountId
    ) {
    }
}
