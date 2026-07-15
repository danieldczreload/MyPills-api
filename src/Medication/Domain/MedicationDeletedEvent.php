<?php

declare(strict_types=1);

namespace Medication\Domain;

use Shared\Domain\DomainEvent;

final class MedicationDeletedEvent extends DomainEvent
{
    public function __construct(
        public readonly string $medicationId,
        public readonly string $profileId
    ) {
        parent::__construct();
    }
}
