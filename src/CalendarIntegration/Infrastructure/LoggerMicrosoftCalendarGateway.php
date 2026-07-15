<?php

declare(strict_types=1);

namespace CalendarIntegration\Infrastructure;

use CalendarIntegration\Domain\CalendarGateway;
use Psr\Log\LoggerInterface;

final class LoggerMicrosoftCalendarGateway implements CalendarGateway
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    public function upsertEvent(
        string $refreshToken,
        string $title,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        string $description
    ): string {
        $eventId = 'microsoft_mock_' . bin2hex(random_bytes(8));
        $this->logger->info(sprintf(
            'Microsoft Calendar Event Upserted (mock ID: %s): Title: "%s", Start: "%s", End: "%s", Desc: "%s"',
            $eventId,
            $title,
            $start->format(\DateTimeInterface::ATOM),
            $end->format(\DateTimeInterface::ATOM),
            $description
        ));

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
