<?php

declare(strict_types=1);

namespace Identity\Application\Command;

use Symfony\Component\Validator\Constraints as Assert;

final class RefreshTokenCommand
{
    public function __construct(
        #[Assert\NotBlank]
        public readonly string $refreshToken
    ) {
    }
}
