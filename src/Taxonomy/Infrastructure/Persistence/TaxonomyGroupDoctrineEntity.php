<?php

declare(strict_types=1);

namespace Taxonomy\Infrastructure\Persistence;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'taxonomy_groups')]
#[ORM\Index(columns: ['client_id'], name: 'idx_taxonomy_groups_client_id')]
#[ORM\Index(columns: ['profile_id'], name: 'idx_taxonomy_groups_profile_id')]
class TaxonomyGroupDoctrineEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36, unique: true)]
    private string $id;

    #[ORM\Column(type: 'string', length: 36)]
    private string $profileId;

    #[ORM\Column(type: 'string', length: 32)]
    private string $type;

    #[ORM\Column(type: 'string', length: 120)]
    private string $name;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description;

    #[ORM\Column(type: 'string', length: 80, nullable: true)]
    private ?string $iconName;

    #[ORM\Column(type: 'bigint', nullable: true)]
    private ?int $colorValue;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $isCustom;

    #[ORM\Column(type: 'string', length: 36, nullable: true)]
    private ?string $clientId;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        string $id,
        string $profileId,
        string $type,
        string $name,
        ?string $description,
        ?string $iconName,
        ?int $colorValue,
        bool $isCustom,
        ?string $clientId,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt
    ) {
        $this->id = $id;
        $this->profileId = $profileId;
        $this->type = $type;
        $this->name = $name;
        $this->description = $description;
        $this->iconName = $iconName;
        $this->colorValue = $colorValue;
        $this->isCustom = $isCustom;
        $this->clientId = $clientId;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getProfileId(): string
    {
        return $this->profileId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getIconName(): ?string
    {
        return $this->iconName;
    }

    public function setIconName(?string $iconName): void
    {
        $this->iconName = $iconName;
    }

    public function getColorValue(): ?int
    {
        return $this->colorValue;
    }

    public function setColorValue(?int $colorValue): void
    {
        $this->colorValue = $colorValue;
    }

    public function isCustom(): bool
    {
        return $this->isCustom;
    }

    public function setIsCustom(bool $isCustom): void
    {
        $this->isCustom = $isCustom;
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
}
