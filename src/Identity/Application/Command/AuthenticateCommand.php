<?php

declare(strict_types=1);

namespace Identity\Application\Command;

use Symfony\Component\Validator\Constraints as Assert;

final class AuthenticateCommand
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['google', 'microsoft'])]
        public readonly string $provider,
        #[Assert\NotBlank]
        public readonly string $idToken
    ) {
    }
}
