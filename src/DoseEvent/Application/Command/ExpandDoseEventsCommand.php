<?php

declare(strict_types=1);

namespace DoseEvent\Application\Command;

final class ExpandDoseEventsCommand
{
    public function __construct(
        public readonly ?\DateTimeImmutable $referenceTime = null
    ) {
    }
}
