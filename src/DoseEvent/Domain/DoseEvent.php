<?php

declare(strict_types=1);

namespace DoseEvent\Domain;

use Shared\Domain\ValueObject\DoseEventId;
use Shared\Domain\ValueObject\MedicationId;
use Shared\Domain\ValueObject\ScheduleId;

final class DoseEvent
{
    public function __construct(
        private readonly DoseEventId $id,
        private readonly MedicationId $medicationId,
        private readonly ScheduleId $scheduleId,
        private readonly \DateTimeImmutable $scheduledAt,
        private string $status,
        private ?\DateTimeImmutable $takenAt,
        private ?string $clientId,
        private readonly \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt
    ) {
    }

    public static function create(
        DoseEventId $id,
        MedicationId $medicationId,
        ScheduleId $scheduleId,
        \DateTimeImmutable $scheduledAt,
        string $status = 'pending',
        ?\DateTimeImmutable $takenAt = null,
        ?string $clientId = null
    ): self {
        $now = new \DateTimeImmutable();
        return new self($id, $medicationId, $scheduleId, $scheduledAt, $status, $takenAt, $clientId, $now, $now);
    }

    public function id(): DoseEventId
    {
        return $this->id;
    }

    public function medicationId(): MedicationId
    {
        return $this->medicationId;
    }

    public function scheduleId(): ScheduleId
    {
        return $this->scheduleId;
    }

    public function scheduledAt(): \DateTimeImmutable
    {
        return $this->scheduledAt;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function takenAt(): ?\DateTimeImmutable
    {
        return $this->takenAt;
    }

    public function clientId(): ?string
    {
        return $this->clientId;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function markAs(string $status, ?\DateTimeImmutable $takenAt = null): void
    {
        $this->status = $status;
        $this->takenAt = $takenAt;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
