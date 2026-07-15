<?php

declare(strict_types=1);

namespace Notification\Infrastructure;

use Notification\Domain\PushNotificationGateway;
use Psr\Log\LoggerInterface;

final class LoggerPushNotificationGateway implements PushNotificationGateway
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    public function send(string $token, string $title, string $body, array $data = []): void
    {
        $this->logger->info(sprintf(
            'Push notification sent to token "%s": Title: "%s", Body: "%s", Data: %s',
            $token,
            $title,
            $body,
            json_encode($data)
        ));
    }
}
