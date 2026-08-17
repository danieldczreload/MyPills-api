<?php

declare(strict_types=1);

namespace App\Tests\DoseEvent\Domain;

use DoseEvent\Domain\DoseEvent;
use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\DoseEventId;
use Shared\Domain\ValueObject\MedicationId;
use Shared\Domain\ValueObject\ScheduleId;

final class DoseEventTest extends TestCase
{
    public function testDoseEventLifecycle(): void
    {
        $id = DoseEventId::generate();
        $medicationId = MedicationId::generate();
        $scheduleId = ScheduleId::generate();
        $scheduledAt = new \DateTimeImmutable('2026-08-01 10:00:00');

        $event = DoseEvent::create($id, $medicationId, $scheduleId, $scheduledAt);

        self::assertTrue($event->id()->equals($id));
        self::assertTrue($event->medicationId()->equals($medicationId));
        self::assertTrue($event->scheduleId()->equals($scheduleId));
        self::assertSame($scheduledAt, $event->scheduledAt());
        self::assertSame('pending', $event->status());
        self::assertNull($event->takenAt());
        self::assertNull($event->clientId());
        self::assertNull($event->reminderSentAt());

        $now = new \DateTimeImmutable();
        $event->markReminderSent($now);
        self::assertSame($now, $event->reminderSentAt());

        $takenAt = new \DateTimeImmutable();
        $event->markAs('taken', $takenAt);
        self::assertSame('taken', $event->status());
        self::assertSame($takenAt, $event->takenAt());
    }
}
