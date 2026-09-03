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

    public function testGoogleUpsertEventIncludesIanaTimeZoneAndLocalWallClock(): void
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

        $gateway->upsertEvent(
            'access-token',
            'Take medication',
            new \DateTimeImmutable('2026-08-29T22:25:00+00:00'),
            new \DateTimeImmutable('2026-08-29T22:55:00+00:00'),
            'Instructions',
            null,
            null,
            'America/El_Salvador'
        );

        /** @var array{
         *     start: array{dateTime: string, timeZone: string},
         *     end: array{dateTime: string, timeZone: string}
         * } $payload */
        $payload = json_decode($requests[0][2]['body'], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('America/El_Salvador', $payload['start']['timeZone']);
        self::assertSame('America/El_Salvador', $payload['end']['timeZone']);
        self::assertSame('2026-08-29T16:25:00-06:00', $payload['start']['dateTime']);
        self::assertSame('2026-08-29T16:55:00-06:00', $payload['end']['dateTime']);
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

    public function testMicrosoftWritesUtcEvenWhenIanaTimeZoneIsPassed(): void
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
            'access-token',
            'Take medication',
            new \DateTimeImmutable('2026-08-29T22:25:00+00:00'),
            new \DateTimeImmutable('2026-08-29T22:55:00+00:00'),
            'Instructions',
            null,
            'stable-event-key',
            'America/El_Salvador'
        );

        /** @var array{
         *     start: array{dateTime: string, timeZone: string},
         *     end: array{dateTime: string, timeZone: string}
         * } $payload */
        $payload = json_decode($requests[0][2]['body'], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('2026-08-29T22:25:00', $payload['start']['dateTime']);
        self::assertSame('UTC', $payload['start']['timeZone']);
        self::assertSame('2026-08-29T22:55:00', $payload['end']['dateTime']);
        self::assertSame('UTC', $payload['end']['timeZone']);
    }

    public function testGoogleExchangeServerAuthCodeThrowsWhenNotConfigured(): void
    {
        $gateway = new GoogleCalendarGateway(new MockHttpClient());
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Google Web OAuth client is not configured.');
        $gateway->exchangeServerAuthCode('auth-code');
    }

    public function testGoogleExchangeServerAuthCodeSuccess(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                'access_token' => 'web-access-token',
                'refresh_token' => 'web-refresh-token',
            ], JSON_THROW_ON_ERROR)),
        ]);
        $gateway = new GoogleCalendarGateway(
            $httpClient,
            'client-id',
            'client-secret',
            'https://redirect',
            'web-client-id',
            'web-client-secret'
        );

        $tokens = $gateway->exchangeServerAuthCode('server-auth-code');
        self::assertSame('web-access-token', $tokens->accessToken());
        self::assertSame('web-refresh-token', $tokens->refreshToken());
    }

    public function testGoogleAuthorizationUrlThrowsWhenNotConfigured(): void
    {
        $gateway = new GoogleCalendarGateway(new MockHttpClient());
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Google Calendar OAuth client is not configured.');
        $gateway->authorizationUrl('state', 'challenge');
    }

    public function testGoogleRefreshAccessToken(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                'access_token' => 'new-google-access-token',
            ], JSON_THROW_ON_ERROR)),
        ]);
        $gateway = new GoogleCalendarGateway($httpClient, 'client-id', 'client-secret');

        $tokens = $gateway->refreshAccessToken('google-refresh-token');
        self::assertSame('new-google-access-token', $tokens->accessToken());
    }

    public function testGoogleRefreshPrefersWebClientWhenConfigured(): void
    {
        $clientIds = [];
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$clientIds): MockResponse {
            $clientIds[] = self::clientIdFromOptions($options);

            return new MockResponse(json_encode([
                'access_token' => 'web-refreshed-token',
            ], JSON_THROW_ON_ERROR));
        });
        $gateway = new GoogleCalendarGateway(
            $httpClient,
            'android-client-id',
            '',
            'com.mypills.app://auth',
            'web-client-id',
            'web-client-secret'
        );

        $tokens = $gateway->refreshAccessToken('google-refresh-token');

        self::assertSame('web-refreshed-token', $tokens->accessToken());
        self::assertSame(['web-client-id'], $clientIds);
    }

    public function testGoogleRefreshFallsBackToAndroidClientWhenWebRefreshFails(): void
    {
        $clientIds = [];
        $responses = [
            new MockResponse(json_encode(['error' => 'unauthorized_client'], JSON_THROW_ON_ERROR), ['http_code' => 401]),
            new MockResponse(json_encode(['access_token' => 'android-refreshed-token'], JSON_THROW_ON_ERROR)),
        ];
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$clientIds, &$responses): MockResponse {
            $clientIds[] = self::clientIdFromOptions($options);
            $response = array_shift($responses);
            if (!$response instanceof MockResponse) {
                throw new \LogicException('Test response queue is empty.');
            }

            return $response;
        });
        $gateway = new GoogleCalendarGateway(
            $httpClient,
            'android-client-id',
            '',
            'com.mypills.app://auth',
            'web-client-id',
            'web-client-secret'
        );

        $tokens = $gateway->refreshAccessToken('google-refresh-token');

        self::assertSame('android-refreshed-token', $tokens->accessToken());
        self::assertSame(['web-client-id', 'android-client-id'], $clientIds);
    }

    public function testGoogleRefreshThrowsWhenNoClientIsConfigured(): void
    {
        $gateway = new GoogleCalendarGateway(new MockHttpClient());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Google Calendar OAuth client is not configured.');
        $gateway->refreshAccessToken('google-refresh-token');
    }

    public function testGoogleUpsertEventRetriesAsCreateOn404(): void
    {
        $requests = [];
        $responses = [
            new MockResponse('', ['http_code' => 404]),
            new MockResponse(json_encode(['id' => 'created-event-id'], JSON_THROW_ON_ERROR), ['http_code' => 200]),
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
            'old-nonexistent-id'
        );

        self::assertSame('created-event-id', $eventId);
        self::assertSame('PATCH', $requests[0][0]);
        self::assertSame('POST', $requests[1][0]);
    }

    public function testGoogleUpsertEventThrowsOnServerError(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('Internal Server Error', ['http_code' => 500]),
        ]);
        $gateway = new GoogleCalendarGateway($httpClient, 'client-id', 'client-secret');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Google Calendar API failed with status 500');

        $gateway->upsertEvent(
            'access-token',
            'Take medication',
            new \DateTimeImmutable('2026-08-03T08:00:00+00:00'),
            new \DateTimeImmutable('2026-08-03T08:30:00+00:00'),
            'Instructions'
        );
    }

    public function testGoogleUpsertEventThrowsWhenEventHasNoId(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode(['summary' => 'Take medication'], JSON_THROW_ON_ERROR)),
        ]);
        $gateway = new GoogleCalendarGateway($httpClient, 'client-id', 'client-secret');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Google Calendar API returned an event without an ID.');

        $gateway->upsertEvent(
            'access-token',
            'Take medication',
            new \DateTimeImmutable('2026-08-03T08:00:00+00:00'),
            new \DateTimeImmutable('2026-08-03T08:30:00+00:00'),
            'Instructions'
        );
    }

    public function testGoogleDeleteEventSuccessAnd404(): void
    {
        $this->expectNotToPerformAssertions();
        $httpClient = new MockHttpClient([
            new MockResponse('', ['http_code' => 204]),
            new MockResponse('', ['http_code' => 200]),
            new MockResponse('', ['http_code' => 404]),
        ]);
        $gateway = new GoogleCalendarGateway($httpClient, 'client-id', 'client-secret');

        $gateway->deleteEvent('access-token', 'evt-1');
        $gateway->deleteEvent('access-token', 'evt-2');
        $gateway->deleteEvent('access-token', 'evt-3');
    }

    public function testGoogleDeleteEventThrowsOnError(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('', ['http_code' => 500]),
        ]);
        $gateway = new GoogleCalendarGateway($httpClient, 'client-id', 'client-secret');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Google Calendar API delete failed with status 500.');

        $gateway->deleteEvent('access-token', 'evt-err');
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function clientIdFromOptions(array $options): string
    {
        $body = $options['body'] ?? '';
        if (is_array($body)) {
            $clientId = $body['client_id'] ?? null;

            return is_string($clientId) ? $clientId : '';
        }
        if (!is_string($body)) {
            return '';
        }

        parse_str($body, $parsed);
        $clientId = $parsed['client_id'] ?? null;

        return is_string($clientId) ? $clientId : '';
    }

    public function testMicrosoftExchangeAuthorizationCodeAndRefresh(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode(['access_token' => 'ms-access', 'refresh_token' => 'ms-refresh'], JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode(['access_token' => 'ms-new-access'], JSON_THROW_ON_ERROR)),
        ]);
        $gateway = new MicrosoftCalendarGateway($httpClient, 'client-id', 'client-secret', 'common', 'https://redirect');

        $tokens = $gateway->exchangeAuthorizationCode('code', str_repeat('v', 43));
        self::assertSame('ms-access', $tokens->accessToken());
        self::assertSame('ms-refresh', $tokens->refreshToken());

        $refreshed = $gateway->refreshAccessToken('ms-refresh');
        self::assertSame('ms-new-access', $refreshed->accessToken());
    }

    public function testMicrosoftUpsertEventRetriesAsCreateOn404(): void
    {
        $requests = [];
        $responses = [
            new MockResponse('', ['http_code' => 404]),
            new MockResponse(json_encode(['id' => 'ms-created-id'], JSON_THROW_ON_ERROR), ['http_code' => 200]),
        ];
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$requests, &$responses): MockResponse {
            $requests[] = [$method, $url, $options];
            $response = array_shift($responses);
            if (!$response instanceof MockResponse) {
                throw new \LogicException('Test response queue is empty.');
            }

            return $response;
        });
        $gateway = new MicrosoftCalendarGateway($httpClient, 'client-id', 'client-secret', 'common', 'https://redirect');

        $eventId = $gateway->upsertEvent(
            'access-token',
            'Take medication',
            new \DateTimeImmutable('2026-08-03T08:00:00+00:00'),
            new \DateTimeImmutable('2026-08-03T08:30:00+00:00'),
            'Instructions',
            'old-ms-id'
        );

        self::assertSame('ms-created-id', $eventId);
        self::assertSame('PATCH', $requests[0][0]);
        self::assertSame('POST', $requests[1][0]);
    }

    public function testMicrosoftUpsertEventThrowsOnServerError(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('Server Error', ['http_code' => 502]),
        ]);
        $gateway = new MicrosoftCalendarGateway($httpClient, 'client-id', 'client-secret', 'common', 'https://redirect');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Microsoft Graph API failed with status 502.');

        $gateway->upsertEvent(
            'access-token',
            'Take medication',
            new \DateTimeImmutable('2026-08-03T08:00:00+00:00'),
            new \DateTimeImmutable('2026-08-03T08:30:00+00:00'),
            'Instructions'
        );
    }

    public function testMicrosoftUpsertEventThrowsWhenEventHasNoId(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode(['subject' => 'Take medication'], JSON_THROW_ON_ERROR)),
        ]);
        $gateway = new MicrosoftCalendarGateway($httpClient, 'client-id', 'client-secret', 'common', 'https://redirect');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Microsoft Graph API returned an event without an ID.');

        $gateway->upsertEvent(
            'access-token',
            'Take medication',
            new \DateTimeImmutable('2026-08-03T08:00:00+00:00'),
            new \DateTimeImmutable('2026-08-03T08:30:00+00:00'),
            'Instructions'
        );
    }

    public function testMicrosoftDeleteEventThrowsOnError(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('', ['http_code' => 500]),
        ]);
        $gateway = new MicrosoftCalendarGateway($httpClient, 'client-id', 'secret', 'common', 'https://redirect');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Microsoft Graph API delete failed with status 500.');

        $gateway->deleteEvent('access-token', 'evt-id');
    }
}
