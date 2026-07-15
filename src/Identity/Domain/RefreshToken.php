<?php

declare(strict_types=1);

namespace Identity\Domain;

use Identity\Domain\ValueObject\RefreshTokenId;
use Shared\Domain\ValueObject\UserId;

final class RefreshToken
{
    public function __construct(
        private readonly RefreshTokenId $id,
        private readonly UserId $accountId,
        private readonly string $token,
        private readonly \DateTimeImmutable $expiresAt,
        private readonly \DateTimeImmutable $createdAt
    ) {
    }

    public static function create(UserId $accountId, string $token, int $ttl = 2592000): self // 30 days
    {
        $now = new \DateTimeImmutable();
        return new self(
            RefreshTokenId::generate(),
            $accountId,
            $token,
            $now->modify('+' . $ttl . ' seconds'),
            $now
        );
    }

    public function id(): RefreshTokenId
    {
        return $this->id;
    }

    public function accountId(): UserId
    {
        return $this->accountId;
    }

    public function token(): string
    {
        return $this->token;
    }

    public function expiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt < new \DateTimeImmutable();
    }
}
