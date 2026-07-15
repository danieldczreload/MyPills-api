<?php

declare(strict_types=1);

namespace Medication\Domain;

use Shared\Domain\ValueObject\MedicationId;
use Shared\Domain\ValueObject\ProfileId;

final class Medication
{
    public function __construct(
        private readonly MedicationId $id,
        private readonly ProfileId $profileId,
        private string $name,
        private string $dosage,
        private ?string $instructions,
        private ?string $photoUrl,
        private ?string $clientId,
        private readonly \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt
    ) {
    }

    public static function create(
        MedicationId $id,
        ProfileId $profileId,
        string $name,
        string $dosage,
        ?string $instructions = null,
        ?string $photoUrl = null,
        ?string $clientId = null
    ): self {
        $now = new \DateTimeImmutable();
        return new self($id, $profileId, $name, $dosage, $instructions, $photoUrl, $clientId, $now, $now);
    }

    public function id(): MedicationId
    {
        return $this->id;
    }

    public function profileId(): ProfileId
    {
        return $this->profileId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function dosage(): string
    {
        return $this->dosage;
    }

    public function instructions(): ?string
    {
        return $this->instructions;
    }

    public function photoUrl(): ?string
    {
        return $this->photoUrl;
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

    public function update(
        string $name,
        string $dosage,
        ?string $instructions,
        ?string $photoUrl
    ): void {
        $this->name = $name;
        $this->dosage = $dosage;
        $this->instructions = $instructions;
        $this->photoUrl = $photoUrl;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
