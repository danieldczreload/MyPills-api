<?php

declare(strict_types=1);

namespace App\Tests\CalendarIntegration\Infrastructure;

use CalendarIntegration\Application\CalendarProviderResolver;
use CalendarIntegration\Domain\CalendarAuthorizationRevoked;
use CalendarIntegration\Domain\CalendarProvider;
use CalendarIntegration\Domain\CalendarProviderName;
use CalendarIntegration\Infrastructure\MicrosoftCalendarGateway;
use CalendarIntegration\Infrastructure\OAuthTokenEndpoint;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class MicrosoftAndOAuthTest extends TestCase
{
    public function testMicrosoftAuthorizationUrlAndValidation(): void
    {
        $gw = new MicrosoftCalendarGateway(new MockHttpClient(), 'client-id', 'secret', 'common', 'https://example.com/cb');
        $url = $gw->authorizationUrl('state-1', 'challenge-1');
        self::assertStringContainsString('client_id=client-id', $url);
        self::assertStringContainsString('redirect_uri=https%3A%2F%2Fexample.com%2Fcb', $url);

        $emptyGw = new MicrosoftCalendarGateway(new MockHttpClient(), '', '');
        $this->expectException(\LogicException::class);
        $emptyGw->authorizationUrl('s', 'c');
    }

    public function testMicrosoftDeleteEvent(): void
    {
        $this->expectNotToPerformAssertions();
        $httpClient = new MockHttpClient([
            new MockResponse('', ['http_code' => 204]),
        ]);
        $gw = new MicrosoftCalendarGateway($httpClient, 'client-id', 'secret', 'common', 'https://example.com/cb');
        $gw->deleteEvent('access-token', 'event-id-123');
    }

    public function testOAuthTokenEndpointExchangeAndRefresh(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode(['access_token' => 'acc-1', 'refresh_token' => 'ref-1'], JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode(['access_token' => 'acc-2'], JSON_THROW_ON_ERROR)),
        ]);
        $endpoint = new OAuthTokenEndpoint($httpClient);

        $tokens = $endpoint->exchangeAuthorizationCode('https://example.com/token', 'Microsoft', 'cid', 'sec', 'https://cb', 'code', 'ver');
        self::assertSame('acc-1', $tokens->accessToken());
        self::assertSame('ref-1', $tokens->refreshToken());

        $refreshed = $endpoint->refreshAccessToken('https://example.com/token', 'Microsoft', 'cid', 'sec', 'ref-1');
        self::assertSame('acc-2', $refreshed->accessToken());
    }

    public function testOAuthTokenEndpointRevokedThrowsException(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode(['error' => 'invalid_grant'], JSON_THROW_ON_ERROR), ['http_code' => 400]),
        ]);
        $endpoint = new OAuthTokenEndpoint($httpClient);

        $this->expectException(CalendarAuthorizationRevoked::class);
        $endpoint->refreshAccessToken('https://example.com/token', 'Microsoft', 'cid', 'sec', 'ref-revoked');
    }

    public function testCalendarProviderResolver(): void
    {
        $google = $this->createMock(CalendarProvider::class);
        $microsoft = $this->createMock(CalendarProvider::class);
        $resolver = new CalendarProviderResolver($google, $microsoft);

        self::assertSame($google, $resolver->resolve(CalendarProviderName::GOOGLE));
        self::assertSame($microsoft, $resolver->resolve(CalendarProviderName::MICROSOFT));
        self::assertSame($google, $resolver->resolveString('google'));
        self::assertSame($microsoft, $resolver->resolveString('microsoft'));
    }
}
