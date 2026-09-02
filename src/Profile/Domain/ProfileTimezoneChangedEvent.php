<?php

declare(strict_types=1);

namespace Profile\Domain;

use Shared\Domain\DomainEvent;

final class ProfileTimezoneChangedEvent extends DomainEvent
{
    public function __construct(
        public readonly string $profileId,
        public readonly string $previousTimezone,
        public readonly string $timezone
    ) {
        parent::__construct();
    }
}
