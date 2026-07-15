<?php

declare(strict_types=1);

namespace Schedule\Application\Command;

final class CreateScheduleCommand
{
    /**
     * @param array<array{hour: int, minute: int}>|null $timesOfDay
     * @param array{hour: int, minute: int}|null $startAt
     * @param array{hour: int, minute: int}|null $endAt
     * @param array<int>|null $daysOfWeek
     */
    public function __construct(
        public readonly string $profileId,
        public readonly string $accountId,
        public readonly string $medicationId,
        public readonly string $type,
        public readonly \DateTimeImmutable $startDate,
        public readonly ?\DateTimeImmutable $endDate = null,
        public readonly ?array $timesOfDay = null,
        public readonly ?int $everyHours = null,
        public readonly ?array $startAt = null,
        public readonly ?array $endAt = null,
        public readonly ?array $daysOfWeek = null,
        public readonly ?string $clientId = null
    ) {
    }
}
