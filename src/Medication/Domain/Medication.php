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
        private ?string $instructions,
        private ?string $photoUrl,
        private ?string $clientId,
        private readonly \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
        private string $form = 'pill',
        private string $colorToken = 'sky'
    ) {
    }

    public static function create(
        MedicationId $id,
        ProfileId $profileId,
        string $name,
        ?string $instructions = null,
        ?string $photoUrl = null,
        ?string $clientId = null,
        string $form = 'pill',
        string $colorToken = 'sky'
    ): self {
        $now = new \DateTimeImmutable();
        return new self($id, $profileId, $name, $instructions, $photoUrl, $clientId, $now, $now, $form, $colorToken);
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

    public function form(): string
    {
        return $this->form;
    }

    public function colorToken(): string
    {
        return $this->colorToken;
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
        ?string $instructions,
        ?string $photoUrl,
        string $form = 'pill',
        string $colorToken = 'sky'
    ): void {
        $this->name = $name;
        $this->instructions = $instructions;
        $this->photoUrl = $photoUrl;
        $this->form = $form;
        $this->colorToken = $colorToken;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
