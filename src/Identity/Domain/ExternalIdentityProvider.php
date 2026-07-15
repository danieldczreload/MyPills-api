<?php

declare(strict_types=1);

namespace Identity\Domain;

use Shared\Domain\Result;

interface ExternalIdentityProvider
{
    /**
     * @return Result<ExternalUser>
     */
    public function verifyToken(string $idToken): Result;
}
