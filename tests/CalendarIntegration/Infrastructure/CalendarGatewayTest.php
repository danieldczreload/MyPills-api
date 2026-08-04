<?php

declare(strict_types=1);

namespace App\Tests\CalendarIntegration\Infrastructure;

use CalendarIntegration\Infrastructure\GoogleCalendarGateway;
use CalendarIntegration\Infrastructure\MicrosoftCalendarGateway;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class CalendarGatewayTest extends TestCase
{
    public function testGoogleBuildsPublicClientPkceAuthorizationUrl(): void
    {
        $gateway = new GoogleCalendarGateway(
            new MockHttpClient(),
            'android-client-id',
            '',
            'com.example.mypills:/oauth2redirect'
        );

        $url = $gateway->authorizationUrl('state-value', 'challenge-value');

        self::assertStringContainsString('client_id=android-client-id', $url);
        self::assertStringContainsString('code_challenge=challenge-value', $url);
        self::assertStringContainsString('code_challenge_method=S256', $url);
        self::assertStringContainsString('redirect_uri=com.example.mypills%3A%2Foauth2redirect', $url);
        self::assertStringNotContainsString('client_secret', $url);
    }

    public function testGoogleExchangesAuthorizationCodeWithoutAClientSecret(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                'access_token' => 'google-access-token',
                'refresh_token' => 'google-refresh-token',
            ], JSON_THROW_ON_ERROR)),
        ]);
        $gateway = new GoogleCalendarGateway(
            $httpClient,
            'android-client-id',
            '',
            'com.example.mypills:/oauth2redirect'
        );

        $tokens = $gateway->exchangeAuthorizationCode('authorization-code', str_repeat('v', 43));

        self::assertSame('google-refresh-token', $tokens->refreshToken());
    }

    public function testGoogleUpdatesAnExistingEventInsteadOfCreatingADuplicate(): void
    {
        $requests = [];
        $responses = [
            new MockResponse(json_encode(['id' => 'google-event-id'], JSON_THROW_ON_ERROR)),
        ];
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$requests, &$responses): MockResponse {
            $requests[] = [$method, $url, $options];
            $response = array_shift($responses);
            if (!$response instanceof MockResponse) {
                throw new \LogicException('Test response queue is empty.');
            }

            return $response;
        });
        $gateway = new GoogleCalendarGateway($httpClient, 'client-id', 'client-secret');

        $eventId = $gateway->upsertEvent(
            'refresh-token',
            'Take medication',
            new \DateTimeImmutable('2026-08-03T08:00:00+00:00'),
            new \DateTimeImmutable('2026-08-03T08:30:00+00:00'),
            'Instructions',
            'google-event-id'
        );

        self::assertSame('google-event-id', $eventId);
        self::assertSame('PATCH', $requests[0][0]);
        self::assertStringContainsString('/events/google-event-id', $requests[0][1]);
    }

    public function testGoogleReusesAnEventWhenAnIdempotentCreateWasRetried(): void
    {
        $requests = [];
        $responses = [
            new MockResponse('', ['http_code' => 409]),
            new MockResponse(json_encode(['id' => 'stable-event-id'], JSON_THROW_ON_ERROR)),
        ];
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$requests, &$responses): MockResponse {
            $requests[] = [$method, $url, $options];
            $response = array_shift($responses);
            if (!$response instanceof MockResponse) {
                throw new \LogicException('Test response queue is empty.');
            }

            return $response;
        });
        $gateway = new GoogleCalendarGateway($httpClient, 'client-id', 'client-secret');

        $eventId = $gateway->upsertEvent(
            'access-token',
            'Take medication',
            new \DateTimeImmutable('2026-08-03T08:00:00+00:00'),
            new \DateTimeImmutable('2026-08-03T08:30:00+00:00'),
            'Instructions',
            null,
            'stable-event-id'
        );

        self::assertSame('stable-event-id', $eventId);
        self::assertSame('POST', $requests[0][0]);
        /** @var array{id: string} $payload */
        $payload = json_decode($requests[0][2]['body'], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('stable-event-id', $payload['id']);
        self::assertSame('GET', $requests[1][0]);
    }

    public function testMicrosoftCreatesAnEventWithUtcDateTimes(): void
    {
        $requests = [];
        $responses = [
            new MockResponse(json_encode(['id' => 'microsoft-event-id'], JSON_THROW_ON_ERROR)),
        ];
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$requests, &$responses): MockResponse {
            $requests[] = [$method, $url, $options];
            $response = array_shift($responses);
            if (!$response instanceof MockResponse) {
                throw new \LogicException('Test response queue is empty.');
            }

            return $response;
        });
        $gateway = new MicrosoftCalendarGateway($httpClient, 'client-id', 'client-secret', 'common');

        $gateway->upsertEvent(
            'refresh-token',
            'Take medication',
            new \DateTimeImmutable('2026-08-03T08:00:00-06:00'),
            new \DateTimeImmutable('2026-08-03T08:30:00-06:00'),
            'Instructions',
            null,
            'stable-event-key'
        );

        self::assertSame('POST', $requests[0][0]);
        /** @var array{
         *     subject: string,
         *     start: array{dateTime: string, timeZone: string},
         *     transactionId: string
         * } $payload */
        $payload = json_decode($requests[0][2]['body'], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Take medication', $payload['subject']);
        self::assertSame('2026-08-03T14:00:00', $payload['start']['dateTime']);
        self::assertSame('UTC', $payload['start']['timeZone']);
        self::assertSame('stable-event-key', $payload['transactionId']);
    }
}
