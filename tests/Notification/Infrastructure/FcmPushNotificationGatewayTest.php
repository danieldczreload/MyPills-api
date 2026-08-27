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

        // Second send reuses cached token
        $responses[] = new MockResponse(json_encode(['name' => 'projects/demo/messages/2'], JSON_THROW_ON_ERROR));
        $gateway->send('fcm-token-2', 'Title', 'Body');
        self::assertCount(3, $requests); // No second OAuth request
    }

    public function testNotConfiguredThrowsLogicException(): void
    {
        $gateway = new FcmPushNotificationGateway(new MockHttpClient(), '', '', '');
        $this->expectException(\LogicException::class);
        $gateway->send('token', 'title', 'body');
    }

    public function testInvalidArgumentsThrowExceptions(): void
    {
        $privateKey = $this->createPrivateKey();
        $gateway = new FcmPushNotificationGateway(new MockHttpClient(), 'demo', 'email@test.com', $privateKey);

        $this->expectException(\InvalidArgumentException::class);
        $gateway->send('', 'title', 'body');
    }

    public function testNonScalarDataThrowsException(): void
    {
        $privateKey = $this->createPrivateKey();
        $gateway = new FcmPushNotificationGateway(new MockHttpClient(), 'demo', 'email@test.com', $privateKey);

        $this->expectException(\InvalidArgumentException::class);
        $gateway->send('token', 'title', 'body', ['nested' => ['array']]);
    }

    public function testUnregisteredTokenThrowsInvalidDeviceToken(): void
    {
        $privateKey = $this->createPrivateKey();
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode(['access_token' => 'tok', 'expires_in' => 3600], JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode([
                'error' => [
                    'status' => 'NOT_FOUND',
                    'details' => [['errorCode' => 'UNREGISTERED']],
                ],
            ], JSON_THROW_ON_ERROR), ['http_code' => 404]),
        ]);
        $gateway = new FcmPushNotificationGateway($httpClient, 'demo', 'email@test.com', $privateKey);

        $this->expectException(\Notification\Domain\InvalidDeviceToken::class);
        $gateway->send('token', 'title', 'body');
    }

    public function testServerErrorThrowsRuntimeException(): void
    {
        $privateKey = $this->createPrivateKey();
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode(['access_token' => 'tok', 'expires_in' => 3600], JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode(['error' => ['status' => 'INTERNAL']], JSON_THROW_ON_ERROR), ['http_code' => 500]),
        ]);
        $gateway = new FcmPushNotificationGateway($httpClient, 'demo', 'email@test.com', $privateKey);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('FCM Push notification failed with status 500.');
        $gateway->send('token', 'title', 'body');
    }

    public function testOAuthTokenFailureThrowsRuntimeException(): void
    {
        $privateKey = $this->createPrivateKey();
        $httpClient = new MockHttpClient([
            new MockResponse('Bad Request', ['http_code' => 400]),
        ]);
        $gateway = new FcmPushNotificationGateway($httpClient, 'demo', 'email@test.com', $privateKey);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Firebase OAuth token request failed with status 400.');
        $gateway->send('token', 'title', 'body');
    }

    public function testOAuthResponseMissingTokenThrowsRuntimeException(): void
    {
        $privateKey = $this->createPrivateKey();
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode(['token_type' => 'Bearer'], JSON_THROW_ON_ERROR)),
        ]);
        $gateway = new FcmPushNotificationGateway($httpClient, 'demo', 'email@test.com', $privateKey);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Firebase OAuth response did not contain an access token.');
        $gateway->send('token', 'title', 'body');
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
