<?php

declare(strict_types=1);

namespace DoseEvent\Infrastructure\Persistence;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'dose_events')]
#[ORM\Index(columns: ['client_id'], name: 'idx_dose_events_client_id')]
class DoseEventDoctrineEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36, unique: true)]
    private string $id;

    #[ORM\Column(type: 'string', length: 36)]
    private string $medicationId;

    #[ORM\Column(type: 'string', length: 36)]
    private string $scheduleId;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $scheduledAt;

    #[ORM\Column(type: 'string', length: 50)]
    private string $status;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $takenAt;

    #[ORM\Column(type: 'string', length: 36, nullable: true)]
    private ?string $clientId;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $reminderSentAt;

    public function __construct(
        string $id,
        string $medicationId,
        string $scheduleId,
        \DateTimeImmutable $scheduledAt,
        string $status,
        ?\DateTimeImmutable $takenAt,
        ?string $clientId,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
        ?\DateTimeImmutable $reminderSentAt = null
    ) {
        $this->id = $id;
        $this->medicationId = $medicationId;
        $this->scheduleId = $scheduleId;
        $this->scheduledAt = $scheduledAt;
        $this->status = $status;
        $this->takenAt = $takenAt;
        $this->clientId = $clientId;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
        $this->reminderSentAt = $reminderSentAt;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getMedicationId(): string
    {
        return $this->medicationId;
    }

    public function getScheduleId(): string
    {
        return $this->scheduleId;
    }

    public function getScheduledAt(): \DateTimeImmutable
    {
        return $this->scheduledAt;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getTakenAt(): ?\DateTimeImmutable
    {
        return $this->takenAt;
    }

    public function setTakenAt(?\DateTimeImmutable $takenAt): void
    {
        $this->takenAt = $takenAt;
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

    public function getReminderSentAt(): ?\DateTimeImmutable
    {
        return $this->reminderSentAt;
    }

    public function setReminderSentAt(?\DateTimeImmutable $reminderSentAt): void
    {
        $this->reminderSentAt = $reminderSentAt;
    }
}
