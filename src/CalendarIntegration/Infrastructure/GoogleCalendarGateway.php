<?php

declare(strict_types=1);

namespace CalendarIntegration\Infrastructure;

use CalendarIntegration\Domain\CalendarOAuthTokens;
use CalendarIntegration\Domain\CalendarProvider;
use CalendarIntegration\Domain\ServerAuthCodeExchanger;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class GoogleCalendarGateway implements CalendarProvider, ServerAuthCodeExchanger
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const PROVIDER_NAME = 'Google';

    private readonly OAuthTokenEndpoint $tokenEndpoint;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $clientId = '',
        private readonly string $clientSecret = '',
        private readonly string $redirectUri = '',
        private readonly string $webClientId = '',
        private readonly string $webClientSecret = '',
        private readonly ?LoggerInterface $logger = null
    ) {
        $this->tokenEndpoint = new OAuthTokenEndpoint($httpClient, $logger);
    }

    /**
     * Exchanges a server auth code issued by the Google Sign-In SDK on the
     * device. The code is bound to the Web application client; the redirect
     * URI must be sent as an empty string per Google's native-app flow.
     */
    public function exchangeServerAuthCode(string $serverAuthCode): CalendarOAuthTokens
    {
        if ($this->webClientId === '' || $this->webClientSecret === '') {
            throw new \LogicException('Google Web OAuth client is not configured.');
        }

        return $this->tokenEndpoint->exchangeAuthorizationCode(
            self::TOKEN_URL,
            self::PROVIDER_NAME,
            $this->webClientId,
            $this->webClientSecret,
            '',
            $serverAuthCode,
            ''
        );
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
        $clients = $this->tokenClients();
        if ($clients === []) {
            throw new \LogicException('Google Calendar OAuth client is not configured.');
        }

        $lastException = new \RuntimeException('Failed to refresh Google OAuth token.');
        foreach ($clients as [$clientId, $clientSecret]) {
            try {
                return $this->tokenEndpoint->refreshAccessToken(
                    self::TOKEN_URL,
                    self::PROVIDER_NAME,
                    $clientId,
                    $clientSecret,
                    $refreshToken
                );
            } catch (\Throwable $exception) {
                $lastException = $exception;
            }
        }

        throw $lastException;
    }

    /**
     * Native google_sign_in exchanges a serverAuthCode with the Web client, so
     * refresh tokens are bound to GOOGLE_WEB_CLIENT_ID. Legacy PKCE links used
     * the Android public client. Try Web first, then Android.
     *
     * @return list<array{0: string, 1: string}>
     */
    private function tokenClients(): array
    {
        $clients = [];

        if ($this->webClientId !== '') {
            $clients[] = [$this->webClientId, $this->webClientSecret];
        }

        if ($this->clientId !== '' && $this->clientId !== $this->webClientId) {
            $clients[] = [$this->clientId, $this->clientSecret];
        }

        return $clients;
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
        $collectionUrl = 'https://www.googleapis.com/calendar/v3/calendars/primary/events';
        $url = $isCreate ? $collectionUrl : $collectionUrl . '/' . rawurlencode($externalEventId);
        $zone = new \DateTimeZone($timeZone);
        $event = [
            'summary' => $title,
            'description' => $description,
            'start' => [
                'dateTime' => $start->setTimezone($zone)->format(\DateTimeInterface::ATOM),
                'timeZone' => $timeZone,
            ],
            'end' => [
                'dateTime' => $end->setTimezone($zone)->format(\DateTimeInterface::ATOM),
                'timeZone' => $timeZone,
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
        $this->logCalendarResponse($isCreate ? 'POST' : 'PATCH', $url, $response);

        if ($response->getStatusCode() === 404 && $externalEventId !== null) {
            return $this->upsertEvent($accessToken, $title, $start, $end, $description, null, $idempotencyKey, $timeZone);
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
                'Google Calendar API failed with status %d: %s',
                $response->getStatusCode(),
                substr($response->getContent(false), 0, 500)
            ));
        }

        $data = $response->toArray();

        if (!is_string($data['id'] ?? null) || $data['id'] === '') {
            throw new \RuntimeException('Google Calendar API returned an event without an ID.');
        }

        return $data['id'];
    }

    private function logCalendarResponse(string $method, string $url, ResponseInterface $response): void
    {
        if ($this->logger === null) {
            return;
        }

        $status = $response->getStatusCode();
        $body = substr($response->getContent(false), 0, 800);
        error_log(sprintf('GOOGLE_CALENDAR %s %s status=%d body=%s', $method, $url, $status, $body));
        $this->logger->info('Google Calendar API response.', [
            'method' => $method,
            'url' => $url,
            'status' => $status,
            'body' => $body,
        ]);
    }

    public function deleteEvent(string $accessToken, string $externalEventId): void
    {
        $url = 'https://www.googleapis.com/calendar/v3/calendars/primary/events/' . rawurlencode($externalEventId);
        $response = $this->httpClient->request(
            'DELETE',
            $url,
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
            ]
        );
        $this->logCalendarResponse('DELETE', $url, $response);

        // 404 is acceptable when deleting an already deleted event
        if ($response->getStatusCode() !== 204 && $response->getStatusCode() !== 200 && $response->getStatusCode() !== 404) {
            throw new \RuntimeException(sprintf('Google Calendar API delete failed with status %d.', $response->getStatusCode()));
        }
    }
}
