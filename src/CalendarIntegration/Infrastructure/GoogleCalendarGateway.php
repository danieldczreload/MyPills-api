<?php

declare(strict_types=1);

namespace CalendarIntegration\Infrastructure;

use CalendarIntegration\Domain\CalendarOAuthTokens;
use CalendarIntegration\Domain\CalendarProvider;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class GoogleCalendarGateway implements CalendarProvider
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const PROVIDER_NAME = 'Google';

    private readonly OAuthTokenEndpoint $tokenEndpoint;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $clientId = '',
        private readonly string $clientSecret = '',
        private readonly string $redirectUri = ''
    ) {
        $this->tokenEndpoint = new OAuthTokenEndpoint($httpClient);
    }

    public function authorizationUrl(string $state, string $codeChallenge): string
    {
        if ($this->clientId === '' || $this->redirectUri === '') {
            throw new \LogicException('Google Calendar OAuth client is not configured.');
        }

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/calendar',
            'access_type' => 'offline',
            'include_granted_scopes' => 'true',
            'prompt' => 'consent',
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function exchangeAuthorizationCode(string $code, string $codeVerifier): CalendarOAuthTokens
    {
        return $this->tokenEndpoint->exchangeAuthorizationCode(
            self::TOKEN_URL,
            self::PROVIDER_NAME,
            $this->clientId,
            $this->clientSecret,
            $this->redirectUri,
            $code,
            $codeVerifier
        );
    }

    public function refreshAccessToken(string $refreshToken): CalendarOAuthTokens
    {
        return $this->tokenEndpoint->refreshAccessToken(
            self::TOKEN_URL,
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
        ?string $idempotencyKey = null
    ): string {
        $isCreate = $externalEventId === null;
        $collectionUrl = 'https://www.googleapis.com/calendar/v3/calendars/primary/events';
        $url = $isCreate ? $collectionUrl : $collectionUrl . '/' . rawurlencode($externalEventId);
        $event = [
            'summary' => $title,
            'description' => $description,
            'start' => [
                'dateTime' => $start->format(\DateTimeInterface::ATOM),
            ],
            'end' => [
                'dateTime' => $end->format(\DateTimeInterface::ATOM),
            ],
        ];

        if ($isCreate && $idempotencyKey !== null) {
            $event['id'] = $idempotencyKey;
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
            return $this->upsertEvent($accessToken, $title, $start, $end, $description, null, $idempotencyKey);
        }

        if ($response->getStatusCode() === 409 && $isCreate && $idempotencyKey !== null) {
            $existingResponse = $this->httpClient->request(
                'GET',
                $collectionUrl . '/' . rawurlencode($idempotencyKey),
                ['headers' => ['Authorization' => 'Bearer ' . $accessToken]]
            );

            if ($existingResponse->getStatusCode() >= 200 && $existingResponse->getStatusCode() < 300) {
                $existingData = $existingResponse->toArray();
                if (is_string($existingData['id'] ?? null) && $existingData['id'] !== '') {
                    return $existingData['id'];
                }
            }
        }

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw new \RuntimeException(sprintf(
                'Google Calendar API failed with status %d.',
                $response->getStatusCode()
            ));
        }

        $data = $response->toArray();

        if (!is_string($data['id'] ?? null) || $data['id'] === '') {
            throw new \RuntimeException('Google Calendar API returned an event without an ID.');
        }

        return $data['id'];
    }

    public function deleteEvent(string $accessToken, string $externalEventId): void
    {
        $response = $this->httpClient->request(
            'DELETE',
            'https://www.googleapis.com/calendar/v3/calendars/primary/events/' . rawurlencode($externalEventId),
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
            ]
        );

        // 404 is acceptable when deleting an already deleted event
        if ($response->getStatusCode() !== 204 && $response->getStatusCode() !== 200 && $response->getStatusCode() !== 404) {
            throw new \RuntimeException(sprintf('Google Calendar API delete failed with status %d.', $response->getStatusCode()));
        }
    }
}
