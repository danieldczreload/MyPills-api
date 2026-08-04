<?php

declare(strict_types=1);

namespace CalendarIntegration\Domain;

use Shared\Domain\ValueObject\ProfileId;
use Shared\Domain\ValueObject\UserId;

final class CalendarAuthorizationRequest
{
    public function __construct(
        private readonly string $id,
        private readonly UserId $accountId,
        private readonly ProfileId $profileId,
        private readonly string $provider,
        private readonly string $stateHash,
        private readonly string $codeChallenge,
        private readonly \DateTimeImmutable $expiresAt,
        private readonly \DateTimeImmutable $createdAt,
        private ?\DateTimeImmutable $usedAt = null
    ) {
    }

    public static function create(
        UserId $accountId,
        ProfileId $profileId,
        string $provider,
        string $stateHash,
        string $codeChallenge,
        \DateTimeImmutable $expiresAt
    ): self {
        return new self(
            self::uuid(),
            $accountId,
            $profileId,
            $provider,
            $stateHash,
            $codeChallenge,
            $expiresAt,
            new \DateTimeImmutable()
        );
    }

    public function id(): string
    {
        return $this->id;
    }

    public function accountId(): UserId
    {
        return $this->accountId;
    }

    public function profileId(): ProfileId
    {
        return $this->profileId;
    }

    public function provider(): string
    {
        return $this->provider;
    }

    public function stateHash(): string
    {
        return $this->stateHash;
    }

    public function codeChallenge(): string
    {
        return $this->codeChallenge;
    }

    public function expiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isUsable(\DateTimeImmutable $now): bool
    {
        return $this->usedAt === null && $this->expiresAt > $now;
    }

    public function usedAt(): ?\DateTimeImmutable
    {
        return $this->usedAt;
    }

    private static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
