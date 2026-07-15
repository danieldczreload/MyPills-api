<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Security;

use Shared\Domain\ValueObject\UserId;
use Symfony\Contracts\Service\ResetInterface;

final class CurrentUserContext implements ResetInterface
{
    private ?UserId $userId = null;

    public function setUserId(UserId $userId): void
    {
        $this->userId = $userId;
    }

    public function getUserId(): ?UserId
    {
        return $this->userId;
    }

    public function isAuthenticated(): bool
    {
        return $this->userId !== null;
    }

    public function reset(): void
    {
        $this->userId = null;
    }
}
