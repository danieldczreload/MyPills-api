<?php

declare(strict_types=1);

namespace Profile\Infrastructure\Persistence;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'sync_tombstones')]
class TombstoneDoctrineEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36, unique: true)]
    private string $id;

    #[ORM\Column(type: 'string', length: 36)]
    private string $profileId;

    #[ORM\Column(type: 'string', length: 50)]
    private string $entityType;

    #[ORM\Column(type: 'string', length: 36)]
    private string $entityId;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $deletedAt;

    public function __construct(
        string $id,
        string $profileId,
        string $entityType,
        string $entityId,
        \DateTimeImmutable $deletedAt
    ) {
        $this->id = $id;
        $this->profileId = $profileId;
        $this->entityType = $entityType;
        $this->entityId = $entityId;
        $this->deletedAt = $deletedAt;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getProfileId(): string
    {
        return $this->profileId;
    }

    public function getEntityType(): string
    {
        return $this->entityType;
    }

    public function getEntityId(): string
    {
        return $this->entityId;
    }

    public function getDeletedAt(): \DateTimeImmutable
    {
        return $this->deletedAt;
    }
}
