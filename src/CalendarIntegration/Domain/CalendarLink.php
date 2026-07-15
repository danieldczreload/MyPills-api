<?php

declare(strict_types=1);

namespace CalendarIntegration\Domain;

use Shared\Domain\ValueObject\ProfileId;

final class CalendarLink
{
    public function __construct(
        private readonly string $id,
        private readonly ProfileId $profileId,
        private readonly string $provider,
        private string $refreshToken,
        private readonly \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt
    ) {
    }

    public static function create(
        ProfileId $profileId,
        string $provider,
        string $refreshToken
    ): self {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));

        $now = new \DateTimeImmutable();
        return new self($uuid, $profileId, $provider, $refreshToken, $now, $now);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function profileId(): ProfileId
    {
        return $this->profileId;
    }

    public function provider(): string
    {
        return $this->provider;
    }

    public function refreshToken(): string
    {
        return $this->refreshToken;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function updateRefreshToken(string $refreshToken): void
    {
        $this->refreshToken = $refreshToken;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
