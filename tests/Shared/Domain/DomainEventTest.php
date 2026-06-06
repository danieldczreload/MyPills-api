<?php

declare(strict_types=1);

namespace App\Tests\Shared\Domain;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Shared\Domain\DomainEvent;

final class DomainEventTest extends TestCase
{
    public function testDomainEventDefaultOccurredAt(): void
    {
        $before = new DateTimeImmutable();
        usleep(1);
        $event = new class () extends DomainEvent {};
        usleep(1);
        $after = new DateTimeImmutable();

        $occurredAt = $event->occurredAt();

        self::assertTrue($occurredAt >= $before);
        self::assertTrue($occurredAt <= $after);
    }

    public function testDomainEventExplicitOccurredAt(): void
    {
        $explicitTime = new DateTimeImmutable('2026-05-19T23:40:44Z');
        $event = new class ($explicitTime) extends DomainEvent {};

        self::assertSame($explicitTime, $event->occurredAt());
    }
}
