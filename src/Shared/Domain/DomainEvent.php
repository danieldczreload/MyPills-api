<?php

declare(strict_types=1);

namespace Shared\Domain;

use DateTimeImmutable;

abstract class DomainEvent
{
    private readonly DateTimeImmutable $occurredAt;

    public function __construct(?DateTimeImmutable $occurredAt = null)
    {
        $this->occurredAt = $occurredAt ?? new DateTimeImmutable();
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
