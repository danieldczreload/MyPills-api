<?php

declare(strict_types=1);

namespace Identity\Domain;

interface RefreshTokenRepository
{
    public function save(RefreshToken $token): void;

    public function findByToken(string $token): ?RefreshToken;

    public function delete(RefreshToken $token): void;
}
