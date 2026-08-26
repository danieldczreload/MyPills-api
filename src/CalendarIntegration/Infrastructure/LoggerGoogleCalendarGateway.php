<?php

declare(strict_types=1);

namespace CalendarIntegration\Infrastructure;

use CalendarIntegration\Domain\CalendarOAuthTokens;
use CalendarIntegration\Domain\CalendarProvider;
use CalendarIntegration\Domain\ServerAuthCodeExchanger;
use Psr\Log\LoggerInterface;

final class LoggerGoogleCalendarGateway implements CalendarProvider, ServerAuthCodeExchanger
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    public function authorizationUrl(string $state, string $codeChallenge): string
    {
        return 'https://accounts.google.com/mock-authorize?state=' . rawurlencode($state);
    }

    public function exchangeAuthorizationCode(string $code, string $codeVerifier): CalendarOAuthTokens
    {
        return new CalendarOAuthTokens('mock-google-access-token', 'mock-google-refresh-token');
    }

    public function exchangeServerAuthCode(string $serverAuthCode): CalendarOAuthTokens
    {
        return new CalendarOAuthTokens('mock-google-access-token', 'mock-google-refresh-token');
    }

    public function refreshAccessToken(string $refreshToken): CalendarOAuthTokens
    {
        return new CalendarOAuthTokens('mock-google-access-token', null);
    }

    public function upsertEvent(
        string $refreshToken,
        string $title,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        string $description,
        ?string $externalEventId = null,
        ?string $idempotencyKey = null
    ): string {
        $eventId = $externalEventId ?? 'google_mock_' . bin2hex(random_bytes(8));
        $this->logger->info('Google Calendar event upserted in test gateway.', ['eventId' => $eventId]);

        return $eventId;
    }

    public function deleteEvent(string $refreshToken, string $externalEventId): void
    {
        $this->logger->info(sprintf(
            'Google Calendar Event Deleted: ID "%s"',
            $externalEventId
        ));
    }
}
