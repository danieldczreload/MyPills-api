<?php

declare(strict_types=1);

namespace Schedule\Domain;

use Schedule\Domain\ValueObject\TimeOfDay;
use Shared\Domain\ValueObject\MedicationId;
use Shared\Domain\ValueObject\ScheduleId;

final class SpecificDaysSchedule extends Schedule
{
    /**
     * @param int[] $daysOfWeek
     * @param TimeOfDay[] $timesOfDay
     */
    public function __construct(
        ScheduleId $id,
        MedicationId $medicationId,
        private readonly array $daysOfWeek,
        private readonly array $timesOfDay,
        \DateTimeImmutable $startDate,
        ?\DateTimeImmutable $endDate,
        ?string $clientId,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt
    ) {
        parent::__construct($id, $medicationId, $startDate, $endDate, $clientId, $createdAt, $updatedAt);
        foreach ($this->daysOfWeek as $day) {
            if ($day < 1 || $day > 7) {
                throw new \InvalidArgumentException('Day of week must be between 1 and 7.');
            }
        }
    }

    /**
     * @return int[]
     */
    public function daysOfWeek(): array
    {
        return $this->daysOfWeek;
    }

    /**
     * @return TimeOfDay[]
     */
    public function timesOfDay(): array
    {
        return $this->timesOfDay;
    }

    public function type(): string
    {
        return 'specific_days';
    }
}
