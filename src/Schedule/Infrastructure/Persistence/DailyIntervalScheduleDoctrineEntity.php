<?php

declare(strict_types=1);

namespace Schedule\Infrastructure\Persistence;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class DailyIntervalScheduleDoctrineEntity extends ScheduleDoctrineEntity
{
    #[ORM\Column(type: 'integer', nullable: true)]
    private int $everyHours;

    #[ORM\Column(type: 'string', length: 5, nullable: true)]
    private string $startAt;

    #[ORM\Column(type: 'string', length: 5, nullable: true)]
    private ?string $endAt;

    public function __construct(
        string $id,
        string $medicationId,
        int $everyHours,
        string $startAt,
        ?string $endAt,
        \DateTimeImmutable $startDate,
        ?\DateTimeImmutable $endDate,
        ?string $clientId,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
        ?string $doseAmount = null,
        ?string $doseUnit = null
    ) {
        parent::__construct($id, $medicationId, $startDate, $endDate, $clientId, $createdAt, $updatedAt, $doseAmount, $doseUnit);
        $this->everyHours = $everyHours;
        $this->startAt = $startAt;
        $this->endAt = $endAt;
    }

    public function getEveryHours(): int
    {
        return $this->everyHours;
    }

    public function getStartAt(): string
    {
        return $this->startAt;
    }

    public function getEndAt(): ?string
    {
        return $this->endAt;
    }
}
