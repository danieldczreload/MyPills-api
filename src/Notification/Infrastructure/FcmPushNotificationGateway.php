<?php

declare(strict_types=1);

namespace Notification\Infrastructure;

use Notification\Domain\PushNotificationGateway;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class FcmPushNotificationGateway implements PushNotificationGateway
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $serverKey = '',
        private readonly string $projectId = ''
    ) {
    }

    public function send(string $token, string $title, string $body, array $data = []): void
    {
        if ($this->serverKey === '' && $this->projectId === '') {
            throw new \LogicException('FCM Gateway requires serverKey or projectId to be configured.');
        }

        // Standard FCM legacy / v1 fallback payload
        $response = $this->httpClient->request(
            'POST',
            'https://fcm.googleapis.com/fcm/send',
            [
                'headers' => [
                    'Authorization' => 'key=' . $this->serverKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'to' => $token,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                        'sound' => 'default',
                    ],
                    'data' => $data,
                    'priority' => 'high',
                ],
            ]
        );

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw new \RuntimeException(sprintf(
                'FCM Push notification failed with status %d: %s',
                $response->getStatusCode(),
                $response->getContent(false)
            ));
        }
    }
}
