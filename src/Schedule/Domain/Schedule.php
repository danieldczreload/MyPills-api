<?php

declare(strict_types=1);

namespace Schedule\Domain;

use Shared\Domain\ValueObject\Dose;
use Shared\Domain\ValueObject\MedicationId;
use Shared\Domain\ValueObject\ScheduleId;

abstract class Schedule
{
    public function __construct(
        protected readonly ScheduleId $id,
        protected readonly MedicationId $medicationId,
        protected readonly \DateTimeImmutable $startDate,
        protected readonly ?\DateTimeImmutable $endDate,
        protected readonly ?string $clientId,
        protected readonly \DateTimeImmutable $createdAt,
        protected readonly \DateTimeImmutable $updatedAt,
        protected readonly ?Dose $dose = null
    ) {
    }

    public function id(): ScheduleId
    {
        return $this->id;
    }

    public function medicationId(): MedicationId
    {
        return $this->medicationId;
    }

    public function startDate(): \DateTimeImmutable
    {
        return $this->startDate;
    }

    public function endDate(): ?\DateTimeImmutable
    {
        return $this->endDate;
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

    public function dose(): ?Dose
    {
        return $this->dose;
    }

    abstract public function type(): string;
}
