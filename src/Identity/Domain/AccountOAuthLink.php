<?php

declare(strict_types=1);

namespace Identity\Domain;

use Identity\Domain\ValueObject\OAuthLinkId;
use Shared\Domain\ValueObject\UserId;

final class AccountOAuthLink
{
    public function __construct(
        private readonly OAuthLinkId $id,
        private readonly UserId $accountId,
        private readonly string $provider,
        private readonly string $externalId,
        private readonly \DateTimeImmutable $createdAt
    ) {
    }

    public static function create(UserId $accountId, string $provider, string $externalId): self
    {
        return new self(
            OAuthLinkId::generate(),
            $accountId,
            $provider,
            $externalId,
            new \DateTimeImmutable()
        );
    }

    public function id(): OAuthLinkId
    {
        return $this->id;
    }

    public function accountId(): UserId
    {
        return $this->accountId;
    }

    public function provider(): string
    {
        return $this->provider;
    }

    public function externalId(): string
    {
        return $this->externalId;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
