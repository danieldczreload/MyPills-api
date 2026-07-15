<?php

declare(strict_types=1);

namespace Identity\Domain;

use Shared\Domain\ValueObject\UserId;
use Shared\Domain\ValueObject\Email;

final class Account
{
    public function __construct(
        private readonly UserId $id,
        private Email $email,
        private readonly \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt
    ) {
    }

    public static function create(UserId $id, Email $email): self
    {
        $now = new \DateTimeImmutable();
        return new self($id, $email, $now, $now);
    }

    public function id(): UserId
    {
        return $this->id;
    }

    public function email(): Email
    {
        return $this->email;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function changeEmail(Email $email): void
    {
        $this->email = $email;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
