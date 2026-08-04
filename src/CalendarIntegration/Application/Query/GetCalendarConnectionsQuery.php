<?php

declare(strict_types=1);

namespace CalendarIntegration\Application\Query;

final class GetCalendarConnectionsQuery
{
    public function __construct(
        public readonly string $profileId,
        public readonly string $accountId
    ) {
    }
}
