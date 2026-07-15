<?php

declare(strict_types=1);

namespace DoseEvent\Application\Command;

final class TrackDoseCommand
{
    public function __construct(
        public readonly string $profileId,
        public readonly string $accountId,
        public readonly string $scheduleId,
        public readonly \DateTimeImmutable $scheduledAt,
        public readonly string $status,
        public readonly ?\DateTimeImmutable $takenAt = null,
        public readonly ?string $clientId = null
    ) {
    }
}
