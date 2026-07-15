<?php

declare(strict_types=1);

namespace Schedule\Application\Query;

final class GetSchedulesQuery
{
    public function __construct(
        public readonly string $profileId,
        public readonly string $accountId
    ) {
    }
}
