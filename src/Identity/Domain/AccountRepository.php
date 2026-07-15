<?php

declare(strict_types=1);

namespace Identity\Domain;

use Shared\Domain\ValueObject\UserId;
use Shared\Domain\ValueObject\Email;

interface AccountRepository
{
    public function save(Account $account): void;

    public function findById(UserId $id): ?Account;

    public function findByEmail(Email $email): ?Account;

    public function findLinked(string $provider, string $externalId): ?Account;

    public function saveLink(AccountOAuthLink $link): void;
}
