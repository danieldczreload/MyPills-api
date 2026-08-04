<?php

declare(strict_types=1);

namespace App\Tests\Notification\Infrastructure;

use Notification\Infrastructure\FcmPushNotificationGateway;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class FcmPushNotificationGatewayTest extends TestCase
{
    public function testSendsUsingFirebaseHttpV1(): void
    {
        $privateKey = $this->createPrivateKey();
        $requests = [];
        $responses = [
            new MockResponse(json_encode(['access_token' => 'firebase-access-token', 'expires_in' => 3600], JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode(['name' => 'projects/demo/messages/1'], JSON_THROW_ON_ERROR)),
        ];
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$requests, &$responses): MockResponse {
            $requests[] = [$method, $url, $options];
            $response = array_shift($responses);
            if (!$response instanceof MockResponse) {
                throw new \LogicException('Test response queue is empty.');
            }

            return $response;
        });
        $gateway = new FcmPushNotificationGateway(
            $httpClient,
            'demo',
            'firebase@example.iam.gserviceaccount.com',
            $privateKey
        );

        $gateway->send('fcm-token', 'Dose reminder', 'Time to take your medication.', ['doseEventId' => 'event-id']);

        self::assertSame('https://oauth2.googleapis.com/token', $requests[0][1]);
        self::assertSame('https://fcm.googleapis.com/v1/projects/demo/messages:send', $requests[1][1]);
        self::assertContains('Authorization: Bearer firebase-access-token', $requests[1][2]['headers']);

        /** @var array{message: array{token: string, data: array{doseEventId: string}}} $payload */
        $payload = json_decode($requests[1][2]['body'], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('fcm-token', $payload['message']['token']);
        self::assertSame('event-id', $payload['message']['data']['doseEventId']);
    }

    private function createPrivateKey(): string
    {
        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        self::assertNotFalse($key);
        self::assertTrue(openssl_pkey_export($key, $privateKey));

        return $privateKey;
    }
}
