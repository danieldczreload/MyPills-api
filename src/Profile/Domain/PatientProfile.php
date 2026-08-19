<?php

declare(strict_types=1);

namespace Profile\Domain;

use Shared\Domain\ValueObject\ProfileId;
use Shared\Domain\ValueObject\UserId;

final class PatientProfile
{
    public function __construct(
        private readonly ProfileId $id,
        private readonly UserId $accountId,
        private string $name,
        private \DateTimeImmutable $birthDate,
        private string $gender,
        private ?string $photoUrl,
        private readonly \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
        private string $timezone = 'UTC'
    ) {
    }

    public static function create(
        ProfileId $id,
        UserId $accountId,
        string $name,
        \DateTimeImmutable $birthDate,
        string $gender,
        ?string $photoUrl = null,
        string $timezone = 'UTC'
    ): self {
        $now = new \DateTimeImmutable();
        return new self($id, $accountId, $name, $birthDate, $gender, $photoUrl, $now, $now, $timezone);
    }

    public function id(): ProfileId
    {
        return $this->id;
    }

    public function accountId(): UserId
    {
        return $this->accountId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function birthDate(): \DateTimeImmutable
    {
        return $this->birthDate;
    }

    public function gender(): string
    {
        return $this->gender;
    }

    public function photoUrl(): ?string
    {
        return $this->photoUrl;
    }

    public function timezone(): string
    {
        return $this->timezone;
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
        \DateTimeImmutable $birthDate,
        string $gender,
        ?string $photoUrl,
        string $timezone = 'UTC'
    ): void {
        $this->name = $name;
        $this->birthDate = $birthDate;
        $this->gender = $gender;
        $this->photoUrl = $photoUrl;
        $this->timezone = $timezone;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
