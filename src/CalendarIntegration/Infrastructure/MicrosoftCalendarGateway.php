<?php

declare(strict_types=1);

namespace CalendarIntegration\Infrastructure;

use CalendarIntegration\Domain\CalendarGateway;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class MicrosoftCalendarGateway implements CalendarGateway
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $clientId = '',
        private readonly string $clientSecret = '',
        private readonly string $tenantId = 'common'
    ) {
    }

    public function upsertEvent(
        string $refreshToken,
        string $title,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        string $description
    ): string {
        $accessToken = $this->getAccessToken($refreshToken);

        $response = $this->httpClient->request(
            'POST',
            'https://graph.microsoft.com/v1.0/me/events',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'subject' => $title,
                    'body' => [
                        'contentType' => 'text',
                        'content' => $description,
                    ],
                    'start' => [
                        'dateTime' => $start->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s'),
                        'timeZone' => 'UTC',
                    ],
                    'end' => [
                        'dateTime' => $end->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s'),
                        'timeZone' => 'UTC',
                    ],
                ],
            ]
        );

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw new \RuntimeException(sprintf(
                'Microsoft Graph API failed with status %d: %s',
                $response->getStatusCode(),
                $response->getContent(false)
            ));
        }

        $data = $response->toArray();

        return (string) ($data['id'] ?? '');
    }

    public function deleteEvent(string $refreshToken, string $externalEventId): void
    {
        $accessToken = $this->getAccessToken($refreshToken);

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
            throw new \RuntimeException(sprintf(
                'Microsoft Graph API delete failed with status %d: %s',
                $response->getStatusCode(),
                $response->getContent(false)
            ));
        }
    }

    private function getAccessToken(string $refreshToken): string
    {
        $url = sprintf('https://login.microsoftonline.com/%s/oauth2/v2.0/token', $this->tenantId);

        $response = $this->httpClient->request(
            'POST',
            $url,
            [
                'body' => [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'refresh_token' => $refreshToken,
                    'grant_type' => 'refresh_token',
                    'scope' => 'https://graph.microsoft.com/.default',
                ],
            ]
        );

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException(sprintf(
                'Failed to refresh Microsoft OAuth token. Status: %d, Response: %s',
                $response->getStatusCode(),
                $response->getContent(false)
            ));
        }

        $data = $response->toArray();
        $accessToken = $data['access_token'] ?? null;

        if ($accessToken === null) {
            throw new \RuntimeException('Microsoft OAuth response did not contain access_token.');
        }

        return (string) $accessToken;
    }
}
