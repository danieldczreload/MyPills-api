<?php

declare(strict_types=1);

namespace CalendarIntegration\Domain;

final readonly class CalendarOAuthTokens
{
    public function __construct(
        private string $accessToken,
        private ?string $refreshToken
    ) {
        if ($this->accessToken === '') {
            throw new \InvalidArgumentException('Access token cannot be empty.');
        }

        if ($this->refreshToken === '') {
            throw new \InvalidArgumentException('Refresh token cannot be empty.');
        }
    }

    public function accessToken(): string
    {
        return $this->accessToken;
    }

    public function refreshToken(): ?string
    {
        return $this->refreshToken;
    }
}
