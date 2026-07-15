<?php

declare(strict_types=1);

namespace Profile\Application\Query;

final class SyncProfileQuery
{
    public function __construct(
        public readonly string $profileId,
        public readonly string $accountId,
        public readonly \DateTimeImmutable $since
    ) {
    }
}
