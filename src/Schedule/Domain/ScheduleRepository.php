<?php

declare(strict_types=1);

namespace Schedule\Domain;

use Shared\Domain\ValueObject\MedicationId;
use Shared\Domain\ValueObject\ScheduleId;

interface ScheduleRepository
{
    public function save(Schedule $schedule): void;

    public function findById(ScheduleId $id): ?Schedule;

    /**
     * @return Schedule[]
     */
    public function findByMedicationId(MedicationId $medicationId): array;

    /**
     * @param MedicationId[] $medicationIds
     * @return Schedule[]
     */
    public function findByMedicationIds(array $medicationIds): array;

    public function findByClientId(string $clientId): ?Schedule;

    public function delete(Schedule $schedule): void;
}
