<?php

declare(strict_types=1);

namespace Schedule\Infrastructure\Persistence;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'schedules')]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'type', type: 'string')]
#[ORM\DiscriminatorMap([
    'daily' => DailyScheduleDoctrineEntity::class,
    'daily_interval' => DailyIntervalScheduleDoctrineEntity::class,
    'specific_days' => SpecificDaysScheduleDoctrineEntity::class,
])]
#[ORM\Index(columns: ['client_id'], name: 'idx_schedules_client_id')]
abstract class ScheduleDoctrineEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36, unique: true)]
    protected string $id;

    #[ORM\Column(type: 'string', length: 36)]
    protected string $medicationId;

    #[ORM\Column(type: 'datetime_immutable')]
    protected \DateTimeImmutable $startDate;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    protected ?\DateTimeImmutable $endDate;

    #[ORM\Column(type: 'string', length: 36, nullable: true)]
    protected ?string $clientId;

    #[ORM\Column(type: 'datetime_immutable')]
    protected \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    protected \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 4, nullable: true)]
    protected ?string $doseAmount;

    #[ORM\Column(type: 'string', length: 16, nullable: true)]
    protected ?string $doseUnit;

    public function __construct(
        string $id,
        string $medicationId,
        \DateTimeImmutable $startDate,
        ?\DateTimeImmutable $endDate,
        ?string $clientId,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
        ?string $doseAmount = null,
        ?string $doseUnit = null
    ) {
        $this->id = $id;
        $this->medicationId = $medicationId;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->clientId = $clientId;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
        $this->doseAmount = $doseAmount;
        $this->doseUnit = $doseUnit;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getMedicationId(): string
    {
        return $this->medicationId;
    }

    public function getStartDate(): \DateTimeImmutable
    {
        return $this->startDate;
    }

    public function getEndDate(): ?\DateTimeImmutable
    {
        return $this->endDate;
    }

    public function getClientId(): ?string
    {
        return $this->clientId;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getDoseAmount(): ?string
    {
        return $this->doseAmount;
    }

    public function setDoseAmount(?string $doseAmount): void
    {
        $this->doseAmount = $doseAmount;
    }

    public function getDoseUnit(): ?string
    {
        return $this->doseUnit;
    }

    public function setDoseUnit(?string $doseUnit): void
    {
        $this->doseUnit = $doseUnit;
    }
}
