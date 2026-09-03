<?php

declare(strict_types=1);

namespace Schedule\Domain;

use Schedule\Domain\ValueObject\TimeOfDay;
use Shared\Domain\ValueObject\Dose;
use Shared\Domain\ValueObject\MedicationId;
use Shared\Domain\ValueObject\ScheduleId;

final class DailySchedule extends Schedule
{
    /**
     * @param TimeOfDay[] $timesOfDay
     */
    public function __construct(
        ScheduleId $id,
        MedicationId $medicationId,
        private readonly array $timesOfDay,
        \DateTimeImmutable $startDate,
        ?\DateTimeImmutable $endDate,
        ?string $clientId,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
        ?Dose $dose = null,
        ?\DateTimeImmutable $cancelledAt = null
    ) {
        parent::__construct($id, $medicationId, $startDate, $endDate, $clientId, $createdAt, $updatedAt, $dose, $cancelledAt);
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
        return 'daily';
    }
}
