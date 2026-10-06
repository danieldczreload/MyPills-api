<?php

declare(strict_types=1);

namespace CalendarIntegration\Infrastructure;

use CalendarIntegration\Domain\CalendarOAuthTokens;
use CalendarIntegration\Domain\CalendarProvider;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

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
        private readonly string $redirectUri = '',
        private readonly ?LoggerInterface $logger = null
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
        // Graph dateTimeTimeZone.timeZone is a Windows-ID allowlist; America/El_Salvador is not on it.
        $utc = new \DateTimeZone('UTC');
        $event = [
            'subject' => $title,
            'body' => [
                'contentType' => 'text',
                'content' => $description,
            ],
            'start' => [
                'dateTime' => $start->setTimezone($utc)->format('Y-m-d\TH:i:s'),
                'timeZone' => 'UTC',
            ],
            'end' => [
                'dateTime' => $end->setTimezone($utc)->format('Y-m-d\TH:i:s'),
                'timeZone' => 'UTC',
            ],
        ];

        if ($isCreate && $idempotencyKey !== null && $idempotencyKey !== '') {
            $event['transactionId'] = self::transactionId($idempotencyKey);
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
            // Graph retains transactionId after the event is deleted.
            return $this->upsertEvent(
                accessToken: $accessToken,
                title: $title,
                start: $start,
                end: $end,
                description: $description,
                externalEventId: null,
                idempotencyKey: null,
                timeZone: $timeZone,
            );
        }

        if (
            $isCreate
            && $idempotencyKey !== null
            && $idempotencyKey !== ''
            && self::isDuplicateTransaction($response)
        ) {
            $existingId = $this->findEventIdByTransactionId($accessToken, self::transactionId($idempotencyKey));
            if ($existingId !== null) {
                return $existingId;
            }
        }

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            $this->logger?->error('Microsoft Graph API request failed.', [
                'status' => $response->getStatusCode(),
                'body' => substr($response->getContent(false), 0, 500),
            ]);

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

    private static function isDuplicateTransaction(ResponseInterface $response): bool
    {
        if ($response->getStatusCode() !== 400) {
            return false;
        }

        try {
            $payload = $response->toArray(false);
        } catch (\JsonException) {
            return false;
        }

        $error = $payload['error'] ?? null;
        if (!is_array($error)) {
            return false;
        }

        $code = $error['code'] ?? null;

        return is_string($code) && $code === 'ErrorDuplicateTransactionId';
    }

    private function findEventIdByTransactionId(string $accessToken, string $transactionId): ?string
    {
        $response = $this->httpClient->request(
            'GET',
            'https://graph.microsoft.com/v1.0/me/events',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
                'query' => [
                    '$filter' => sprintf("transactionId eq '%s'", $transactionId),
                    '$select' => 'id',
                    '$top' => '1',
                ],
            ]
        );

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            return null;
        }

        try {
            $payload = $response->toArray(false);
        } catch (\JsonException) {
            return null;
        }

        $events = $payload['value'] ?? null;
        if (!is_array($events)) {
            return null;
        }

        $first = $events[0] ?? null;
        if (!is_array($first)) {
            return null;
        }

        $id = $first['id'] ?? null;

        return is_string($id) && $id !== '' ? $id : null;
    }

    /**
     * Graph rejects a transactionId that is not an RFC 4122 GUID. The sync key is 32 hex
     * characters, which Google accepts as an event id and Outlook does not.
     */
    private static function transactionId(string $idempotencyKey): string
    {
        $hex = strtolower($idempotencyKey);
        if (preg_match('/^[0-9a-f]{32}$/', $hex) !== 1) {
            throw new \InvalidArgumentException('Microsoft transactionId requires a 32-character hex idempotency key.');
        }

        $variantNibble = dechex((hexdec(substr($hex, 16, 1)) & 0x3) | 0x8);

        return substr($hex, 0, 8)
            . '-' . substr($hex, 8, 4)
            . '-4' . substr($hex, 13, 3)
            . '-' . $variantNibble . substr($hex, 17, 3)
            . '-' . substr($hex, 20, 12);
    }
}
