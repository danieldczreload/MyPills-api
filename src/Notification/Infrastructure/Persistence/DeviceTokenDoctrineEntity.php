<?php

declare(strict_types=1);

namespace Notification\Infrastructure\Persistence;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'device_tokens')]
class DeviceTokenDoctrineEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36, unique: true)]
    private string $id;

    #[ORM\Column(type: 'string', length: 36)]
    private string $accountId;

    #[ORM\Column(type: 'text')]
    private string $token;

    #[ORM\Column(type: 'string', length: 64, unique: true)]
    private string $tokenHash;

    #[ORM\Column(type: 'string', length: 50)]
    private string $platform;

    #[ORM\Column(type: 'string', length: 10)]
    private string $locale;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $id,
        string $accountId,
        string $token,
        string $tokenHash,
        string $platform,
        string $locale,
        \DateTimeImmutable $createdAt
    ) {
        $this->id = $id;
        $this->accountId = $accountId;
        $this->token = $token;
        $this->tokenHash = $tokenHash;
        $this->platform = $platform;
        $this->locale = $locale;
        $this->createdAt = $createdAt;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getAccountId(): string
    {
        return $this->accountId;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function setToken(string $token): void
    {
        $this->token = $token;
    }

    public function setTokenHash(string $tokenHash): void
    {
        $this->tokenHash = $tokenHash;
    }

    public function getPlatform(): string
    {
        return $this->platform;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setPlatform(string $platform): void
    {
        $this->platform = $platform;
    }

    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
