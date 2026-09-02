<?php

declare(strict_types=1);

namespace CalendarIntegration\Infrastructure;

use CalendarIntegration\Domain\CalendarOAuthTokens;
use CalendarIntegration\Domain\CalendarProvider;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class MicrosoftCalendarGateway implements CalendarProvider
{
    private const PROVIDER_NAME = 'Microsoft';
    private const SCOPE = 'openid profile offline_access User.Read Calendars.ReadWrite';

    private readonly OAuthTokenEndpoint $tokenEndpoint;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $clientId = '',
        private readonly string $clientSecret = '',
        private readonly string $tenantId = 'common',
        private readonly string $redirectUri = ''
    ) {
        $this->tokenEndpoint = new OAuthTokenEndpoint($httpClient);
    }

    public function authorizationUrl(string $state, string $codeChallenge): string
    {
        if ($this->clientId === '' || $this->redirectUri === '') {
            throw new \LogicException('Microsoft Calendar OAuth client is not configured.');
        }

        return sprintf(
            'https://login.microsoftonline.com/%s/oauth2/v2.0/authorize?',
            rawurlencode($this->tenantId)
        ) . http_build_query([
            'client_id' => $this->clientId,
            'response_type' => 'code',
            'redirect_uri' => $this->redirectUri,
            'response_mode' => 'query',
            'scope' => self::SCOPE,
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function exchangeAuthorizationCode(string $code, string $codeVerifier): CalendarOAuthTokens
    {
        return $this->tokenEndpoint->exchangeAuthorizationCode(
            $this->tokenUrl(),
            self::PROVIDER_NAME,
            $this->clientId,
            $this->clientSecret,
            $this->redirectUri,
            $code,
            $codeVerifier,
            ['scope' => self::SCOPE]
        );
    }

    public function refreshAccessToken(string $refreshToken): CalendarOAuthTokens
    {
        return $this->tokenEndpoint->refreshAccessToken(
            $this->tokenUrl(),
            self::PROVIDER_NAME,
            $this->clientId,
            $this->clientSecret,
            $refreshToken
        );
    }

    public function upsertEvent(
        string $accessToken,
        string $title,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        string $description,
        ?string $externalEventId = null,
        ?string $idempotencyKey = null,
        string $timeZone = 'UTC'
    ): string {
        $isCreate = $externalEventId === null;
        $collectionUrl = 'https://graph.microsoft.com/v1.0/me/events';
        $url = $isCreate ? $collectionUrl : $collectionUrl . '/' . rawurlencode($externalEventId);
        $zone = new \DateTimeZone($timeZone);
        $event = [
            'subject' => $title,
            'body' => [
                'contentType' => 'text',
                'content' => $description,
            ],
            'start' => [
                'dateTime' => $start->setTimezone($zone)->format('Y-m-d\TH:i:s'),
                'timeZone' => $timeZone,
            ],
            'end' => [
                'dateTime' => $end->setTimezone($zone)->format('Y-m-d\TH:i:s'),
                'timeZone' => $timeZone,
            ],
        ];

        if ($isCreate && $idempotencyKey !== null) {
            $event['transactionId'] = $idempotencyKey;
        }

        $response = $this->httpClient->request(
            $isCreate ? 'POST' : 'PATCH',
            $url,
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $event,
            ]
        );

        if ($response->getStatusCode() === 404 && $externalEventId !== null) {
            return $this->upsertEvent($accessToken, $title, $start, $end, $description, null, $idempotencyKey, $timeZone);
        }

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw new \RuntimeException(sprintf(
                'Microsoft Graph API failed with status %d.',
                $response->getStatusCode()
            ));
        }

        $data = $response->toArray();

        if (!is_string($data['id'] ?? null) || $data['id'] === '') {
            throw new \RuntimeException('Microsoft Graph API returned an event without an ID.');
        }

        return $data['id'];
    }

    public function deleteEvent(string $accessToken, string $externalEventId): void
    {
        $response = $this->httpClient->request(
            'DELETE',
            'https://graph.microsoft.com/v1.0/me/events/' . rawurlencode($externalEventId),
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
            ]
        );

        if ($response->getStatusCode() !== 204 && $response->getStatusCode() !== 200 && $response->getStatusCode() !== 404) {
            throw new \RuntimeException(sprintf('Microsoft Graph API delete failed with status %d.', $response->getStatusCode()));
        }
    }

    private function tokenUrl(): string
    {
        return sprintf('https://login.microsoftonline.com/%s/oauth2/v2.0/token', rawurlencode($this->tenantId));
    }
}
