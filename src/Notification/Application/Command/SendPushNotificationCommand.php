<?php

declare(strict_types=1);

namespace Notification\Application\Command;

final class SendPushNotificationCommand
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public readonly string $accountId,
        public readonly string $title,
        public readonly string $body,
        public readonly array $data = []
    ) {
    }
}
