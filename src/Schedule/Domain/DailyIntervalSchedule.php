<?php

declare(strict_types=1);

namespace Schedule\Domain;

use Schedule\Domain\ValueObject\TimeOfDay;
use Shared\Domain\ValueObject\MedicationId;
use Shared\Domain\ValueObject\ScheduleId;

final class DailyIntervalSchedule extends Schedule
{
    public function __construct(
        ScheduleId $id,
        MedicationId $medicationId,
        private readonly int $everyHours,
        private readonly TimeOfDay $startAt,
        private readonly ?TimeOfDay $endAt,
        \DateTimeImmutable $startDate,
        ?\DateTimeImmutable $endDate,
        ?string $clientId,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt
    ) {
        parent::__construct($id, $medicationId, $startDate, $endDate, $clientId, $createdAt, $updatedAt);
        if ($this->everyHours <= 0) {
            throw new \InvalidArgumentException('everyHours must be positive.');
        }
    }

    public function everyHours(): int
    {
        return $this->everyHours;
    }

    public function startAt(): TimeOfDay
    {
        return $this->startAt;
    }

    public function endAt(): ?TimeOfDay
    {
        return $this->endAt;
    }

    public function type(): string
    {
        return 'daily_interval';
    }
}
