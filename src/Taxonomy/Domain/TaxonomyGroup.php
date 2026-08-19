<?php

declare(strict_types=1);

namespace Taxonomy\Domain;

use Shared\Domain\ValueObject\ProfileId;
use Shared\Domain\ValueObject\TaxonomyGroupId;

final class TaxonomyGroup
{
    public function __construct(
        private readonly TaxonomyGroupId $id,
        private readonly ProfileId $profileId,
        private string $type,
        private string $name,
        private ?string $description,
        private ?string $iconName,
        private ?int $colorValue,
        private bool $isCustom,
        private ?string $clientId,
        private readonly \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt
    ) {
    }

    public static function create(
        TaxonomyGroupId $id,
        ProfileId $profileId,
        string $type,
        string $name,
        ?string $description = null,
        ?string $iconName = null,
        ?int $colorValue = null,
        bool $isCustom = true,
        ?string $clientId = null
    ): self {
        $now = new \DateTimeImmutable();
        return new self(
            $id,
            $profileId,
            $type,
            $name,
            $description,
            $iconName,
            $colorValue,
            $isCustom,
            $clientId,
            $now,
            $now
        );
    }

    public function id(): TaxonomyGroupId
    {
        return $this->id;
    }

    public function profileId(): ProfileId
    {
        return $this->profileId;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function iconName(): ?string
    {
        return $this->iconName;
    }

    public function colorValue(): ?int
    {
        return $this->colorValue;
    }

    public function isCustom(): bool
    {
        return $this->isCustom;
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
        string $type,
        string $name,
        ?string $description,
        ?string $iconName,
        ?int $colorValue,
        bool $isCustom
    ): void {
        $this->type = $type;
        $this->name = $name;
        $this->description = $description;
        $this->iconName = $iconName;
        $this->colorValue = $colorValue;
        $this->isCustom = $isCustom;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
