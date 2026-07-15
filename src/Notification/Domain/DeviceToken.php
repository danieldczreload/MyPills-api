<?php

declare(strict_types=1);

namespace Notification\Domain;

use Shared\Domain\ValueObject\UserId;

final class DeviceToken
{
    public function __construct(
        private readonly string $id,
        private readonly UserId $accountId,
        private readonly string $token,
        private readonly string $platform,
        private readonly string $locale,
        private readonly \DateTimeImmutable $createdAt
    ) {
    }

    public static function create(
        UserId $accountId,
        string $token,
        string $platform,
        string $locale
    ): self {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));

        return new self($uuid, $accountId, $token, $platform, $locale, new \DateTimeImmutable());
    }

    public function id(): string
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

    public function platform(): string
    {
        return $this->platform;
    }

    public function locale(): string
    {
        return $this->locale;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
