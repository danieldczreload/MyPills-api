<?php

declare(strict_types=1);

namespace Notification\Domain;

use Shared\Domain\ValueObject\UserId;

interface NotificationPreferencesRepository
{
    public function save(NotificationPreferences $preferences): void;

    public function findByAccountId(UserId $accountId): ?NotificationPreferences;
}
