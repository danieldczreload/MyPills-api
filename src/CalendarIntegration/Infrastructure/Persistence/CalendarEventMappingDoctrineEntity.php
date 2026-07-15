<?php

declare(strict_types=1);

namespace CalendarIntegration\Infrastructure\Persistence;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'calendar_event_mappings')]
#[ORM\UniqueConstraint(name: 'uniq_dose_event_provider', columns: ['dose_event_id', 'provider'])]
class CalendarEventMappingDoctrineEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36, unique: true)]
    private string $id;

    #[ORM\Column(type: 'string', length: 36)]
    private string $doseEventId;

    #[ORM\Column(type: 'string', length: 50)]
    private string $provider;

    #[ORM\Column(type: 'string', length: 255)]
    private string $externalEventId;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $id,
        string $doseEventId,
        string $provider,
        string $externalEventId,
        \DateTimeImmutable $createdAt
    ) {
        $this->id = $id;
        $this->doseEventId = $doseEventId;
        $this->provider = $provider;
        $this->externalEventId = $externalEventId;
        $this->createdAt = $createdAt;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getDoseEventId(): string
    {
        return $this->doseEventId;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function getExternalEventId(): string
    {
        return $this->externalEventId;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
