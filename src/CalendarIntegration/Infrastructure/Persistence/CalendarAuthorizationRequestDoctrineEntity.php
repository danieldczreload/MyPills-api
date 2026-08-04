<?php

declare(strict_types=1);

namespace CalendarIntegration\Infrastructure\Persistence;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'calendar_authorization_requests')]
#[ORM\Index(name: 'idx_calendar_auth_request_expires_at', columns: ['expires_at'])]
#[ORM\UniqueConstraint(name: 'uniq_calendar_auth_request_state_hash', columns: ['state_hash'])]
class CalendarAuthorizationRequestDoctrineEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36, unique: true)]
    private string $id;

    #[ORM\Column(type: 'string', length: 36)]
    private string $accountId;

    #[ORM\Column(type: 'string', length: 36)]
    private string $profileId;

    #[ORM\Column(type: 'string', length: 20)]
    private string $provider;

    #[ORM\Column(type: 'string', length: 64)]
    private string $stateHash;

    #[ORM\Column(type: 'string', length: 128)]
    private string $codeChallenge;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $usedAt;

    public function __construct(
        string $id,
        string $accountId,
        string $profileId,
        string $provider,
        string $stateHash,
        string $codeChallenge,
        \DateTimeImmutable $expiresAt,
        \DateTimeImmutable $createdAt,
        ?\DateTimeImmutable $usedAt = null
    ) {
        $this->id = $id;
        $this->accountId = $accountId;
        $this->profileId = $profileId;
        $this->provider = $provider;
        $this->stateHash = $stateHash;
        $this->codeChallenge = $codeChallenge;
        $this->expiresAt = $expiresAt;
        $this->createdAt = $createdAt;
        $this->usedAt = $usedAt;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getAccountId(): string
    {
        return $this->accountId;
    }

    public function getProfileId(): string
    {
        return $this->profileId;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function getStateHash(): string
    {
        return $this->stateHash;
    }

    public function getCodeChallenge(): string
    {
        return $this->codeChallenge;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUsedAt(): ?\DateTimeImmutable
    {
        return $this->usedAt;
    }
}
