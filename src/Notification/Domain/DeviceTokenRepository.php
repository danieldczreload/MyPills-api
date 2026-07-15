<?php

declare(strict_types=1);

namespace Notification\Domain;

use Shared\Domain\ValueObject\UserId;

interface DeviceTokenRepository
{
    public function save(DeviceToken $deviceToken): void;

    public function findByToken(string $token): ?DeviceToken;

    /**
     * @return DeviceToken[]
     */
    public function findByAccountId(UserId $accountId): array;

    public function delete(DeviceToken $deviceToken): void;
}
