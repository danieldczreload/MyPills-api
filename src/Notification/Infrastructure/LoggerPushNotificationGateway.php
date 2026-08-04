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
        $fingerprint = substr(hash('sha256', $token), 0, 12);
        $this->logger->info('Push notification dispatched.', [
            'tokenFingerprint' => $fingerprint,
            'dataKeys' => array_keys($data),
        ]);
    }
}
