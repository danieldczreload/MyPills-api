<?php

declare(strict_types=1);

namespace CalendarIntegration\Infrastructure;

use CalendarIntegration\Domain\CalendarGateway;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class GoogleCalendarGateway implements CalendarGateway
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $clientId = '',
        private readonly string $clientSecret = ''
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
            'https://www.googleapis.com/calendar/v3/calendars/primary/events',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'summary' => $title,
                    'description' => $description,
                    'start' => [
                        'dateTime' => $start->format(\DateTimeInterface::ATOM),
                    ],
                    'end' => [
                        'dateTime' => $end->format(\DateTimeInterface::ATOM),
                    ],
                ],
            ]
        );

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw new \RuntimeException(sprintf(
                'Google Calendar API failed with status %d: %s',
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
            'https://www.googleapis.com/calendar/v3/calendars/primary/events/' . rawurlencode($externalEventId),
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
            ]
        );

        // 404 is acceptable when deleting an already deleted event
        if ($response->getStatusCode() !== 204 && $response->getStatusCode() !== 200 && $response->getStatusCode() !== 404) {
            throw new \RuntimeException(sprintf(
                'Google Calendar API delete failed with status %d: %s',
                $response->getStatusCode(),
                $response->getContent(false)
            ));
        }
    }

    private function getAccessToken(string $refreshToken): string
    {
        $response = $this->httpClient->request(
            'POST',
            'https://oauth2.googleapis.com/token',
            [
                'body' => [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'refresh_token' => $refreshToken,
                    'grant_type' => 'refresh_token',
                ],
            ]
        );

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException(sprintf(
                'Failed to refresh Google OAuth token. Status: %d, Response: %s',
                $response->getStatusCode(),
                $response->getContent(false)
            ));
        }

        $data = $response->toArray();
        $accessToken = $data['access_token'] ?? null;

        if ($accessToken === null) {
            throw new \RuntimeException('Google OAuth response did not contain access_token.');
        }

        return (string) $accessToken;
    }
}
