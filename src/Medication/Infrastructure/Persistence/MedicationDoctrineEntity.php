<?php

declare(strict_types=1);

namespace Medication\Infrastructure\Persistence;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'medications')]
#[ORM\Index(columns: ['client_id'], name: 'idx_medications_client_id')]
class MedicationDoctrineEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36, unique: true)]
    private string $id;

    #[ORM\Column(type: 'string', length: 36)]
    private string $profileId;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'string', length: 255)]
    private string $dosage;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $instructions;

    #[ORM\Column(type: 'string', length: 1000, nullable: true)]
    private ?string $photoUrl;

    #[ORM\Column(type: 'string', length: 36, nullable: true)]
    private ?string $clientId;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        string $id,
        string $profileId,
        string $name,
        string $dosage,
        ?string $instructions,
        ?string $photoUrl,
        ?string $clientId,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt
    ) {
        $this->id = $id;
        $this->profileId = $profileId;
        $this->name = $name;
        $this->dosage = $dosage;
        $this->instructions = $instructions;
        $this->photoUrl = $photoUrl;
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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getDosage(): string
    {
        return $this->dosage;
    }

    public function setDosage(string $dosage): void
    {
        $this->dosage = $dosage;
    }

    public function getInstructions(): ?string
    {
        return $this->instructions;
    }

    public function setInstructions(?string $instructions): void
    {
        $this->instructions = $instructions;
    }

    public function getPhotoUrl(): ?string
    {
        return $this->photoUrl;
    }

    public function setPhotoUrl(?string $photoUrl): void
    {
        $this->photoUrl = $photoUrl;
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
