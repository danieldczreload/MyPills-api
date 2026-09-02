<?php

declare(strict_types=1);

namespace CalendarIntegration\Infrastructure;

use CalendarIntegration\Domain\CalendarOAuthTokens;
use CalendarIntegration\Domain\CalendarProvider;
use Psr\Log\LoggerInterface;

final class LoggerMicrosoftCalendarGateway implements CalendarProvider
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    public function authorizationUrl(string $state, string $codeChallenge): string
    {
        return 'https://login.microsoftonline.com/mock-authorize?state=' . rawurlencode($state);
    }

    public function exchangeAuthorizationCode(string $code, string $codeVerifier): CalendarOAuthTokens
    {
        return new CalendarOAuthTokens('mock-microsoft-access-token', 'mock-microsoft-refresh-token');
    }

    public function refreshAccessToken(string $refreshToken): CalendarOAuthTokens
    {
        return new CalendarOAuthTokens('mock-microsoft-access-token', null);
    }

    public function upsertEvent(
        string $refreshToken,
        string $title,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        string $description,
        ?string $externalEventId = null,
        ?string $idempotencyKey = null,
        string $timeZone = 'UTC'
    ): string {
        $eventId = $externalEventId ?? 'microsoft_mock_' . bin2hex(random_bytes(8));
        $this->logger->info('Microsoft Calendar event upserted in test gateway.', ['eventId' => $eventId]);

        return $eventId;
    }

    public function deleteEvent(string $refreshToken, string $externalEventId): void
    {
        $this->logger->info(sprintf(
            'Microsoft Calendar Event Deleted: ID "%s"',
            $externalEventId
        ));
    }
}
