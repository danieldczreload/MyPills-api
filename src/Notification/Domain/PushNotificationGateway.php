<?php

declare(strict_types=1);

namespace Notification\Domain;

interface PushNotificationGateway
{
    /**
     * Sends a push notification to a specific device token.
     *
     * @param string $token The destination FCM token
     * @param string $title Notification title
     * @param string $body Notification body
     * @param array<string, mixed> $data Custom payload data
     */
    public function send(string $token, string $title, string $body, array $data = []): void;
}
