<?php

declare(strict_types=1);

namespace Identity\Application\Command;

final class LogoutCommand
{
    public function __construct(
        public readonly string $refreshToken = '',
        public readonly string $accessToken = '',
    ) {
    }
}
