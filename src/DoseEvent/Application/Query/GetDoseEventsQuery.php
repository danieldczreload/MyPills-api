<?php

declare(strict_types=1);

namespace DoseEvent\Application\Query;

final class GetDoseEventsQuery
{
    public function __construct(
        public readonly string $profileId,
        public readonly string $accountId,
        public readonly \DateTimeImmutable $from,
        public readonly \DateTimeImmutable $to
    ) {
    }
}
