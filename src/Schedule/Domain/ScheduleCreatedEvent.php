<?php

declare(strict_types=1);

namespace Schedule\Domain;

use Shared\Domain\DomainEvent;

final class ScheduleCreatedEvent extends DomainEvent
{
    public function __construct(
        public readonly string $scheduleId,
        public readonly string $medicationId,
        public readonly string $profileId
    ) {
        parent::__construct();
    }
}
