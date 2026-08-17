<?php

declare(strict_types=1);

namespace Notification\Application\Query;

final class GetPreferencesQuery
{
    public function __construct(
        public readonly string $accountId
    ) {
    }
}
