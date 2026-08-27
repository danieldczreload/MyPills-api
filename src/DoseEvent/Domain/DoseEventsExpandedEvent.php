<?php

declare(strict_types=1);

namespace DoseEvent\Domain;

use Shared\Domain\DomainEvent;

final class DoseEventsExpandedEvent extends DomainEvent
{
    public function __construct(
        public readonly string $profileId,
        public readonly string $scheduleId
    ) {
        parent::__construct();
    }
}
