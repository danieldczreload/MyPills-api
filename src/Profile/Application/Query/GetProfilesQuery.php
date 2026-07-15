<?php

declare(strict_types=1);

namespace Profile\Application\Query;

final class GetProfilesQuery
{
    public function __construct(
        public readonly string $accountId
    ) {
    }
}
