<?php

declare(strict_types=1);

namespace DoseEvent\Domain;

use Shared\Domain\ValueObject\DoseEventId;
use Shared\Domain\ValueObject\ScheduleId;

interface DoseEventRepository
{
    public function save(DoseEvent $doseEvent): void;

    public function findById(DoseEventId $id): ?DoseEvent;

    /**
     * @return DoseEvent[]
     */
    public function findByScheduleId(ScheduleId $scheduleId): array;

    /**
     * @param ScheduleId[] $scheduleIds
     * @return DoseEvent[]
     */
    public function findByScheduleIds(array $scheduleIds): array;

    /**
     * @param ScheduleId[] $scheduleIds
     * @return DoseEvent[]
     */
    public function findPendingByScheduleIds(array $scheduleIds): array;

    /**
     * @param ScheduleId[] $scheduleIds
     */
    public function deletePendingByScheduleIds(array $scheduleIds): void;

    public function findByClientId(string $clientId): ?DoseEvent;

    /**
     * @param ScheduleId[] $scheduleIds
     * @return DoseEvent[]
     */
    public function findByScheduleIdsAndRange(array $scheduleIds, \DateTimeImmutable $from, \DateTimeImmutable $to): array;

    public function delete(DoseEvent $doseEvent): void;
}
