<?php

declare(strict_types=1);

namespace Identity\Infrastructure\Persistence;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'account_oauth_links')]
#[ORM\UniqueConstraint(name: 'uniq_provider_external', columns: ['provider', 'external_id'])]
class AccountOAuthLinkDoctrineEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36, unique: true)]
    private string $id;

    #[ORM\Column(type: 'string', length: 36)]
    private string $accountId;

    #[ORM\Column(type: 'string', length: 50)]
    private string $provider;

    #[ORM\Column(type: 'string', length: 255)]
    private string $externalId;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $id, string $accountId, string $provider, string $externalId, \DateTimeImmutable $createdAt)
    {
        $this->id = $id;
        $this->accountId = $accountId;
        $this->provider = $provider;
        $this->externalId = $externalId;
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

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function getExternalId(): string
    {
        return $this->externalId;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
