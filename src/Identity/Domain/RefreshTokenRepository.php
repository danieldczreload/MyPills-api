<?php

declare(strict_types=1);

namespace Identity\Domain;

use Shared\Domain\ValueObject\UserId;

interface RefreshTokenRepository
{
    public function save(RefreshToken $token): void;

    public function findByToken(string $token): ?RefreshToken;

    public function delete(RefreshToken $token): void;

    public function deleteByAccountId(UserId $accountId): void;
}
