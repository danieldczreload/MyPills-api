<?php

declare(strict_types=1);

namespace Schedule\Domain;

use Shared\Domain\DomainEvent;

final class ScheduleDeletedEvent extends DomainEvent
{
    public function __construct(
        public readonly string $scheduleId,
        public readonly string $profileId
    ) {
        parent::__construct();
    }
}
