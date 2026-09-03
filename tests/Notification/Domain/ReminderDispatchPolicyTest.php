<?php

declare(strict_types=1);

namespace App\Tests\Notification\Domain;

use Notification\Domain\ReminderDispatchPolicy;
use PHPUnit\Framework\TestCase;

final class ReminderDispatchPolicyTest extends TestCase
{
    public function testIsDueWhenScheduledAtIsNow(): void
    {
        $now = new \DateTimeImmutable('2026-08-17 12:00:00', new \DateTimeZone('UTC'));

        self::assertTrue(ReminderDispatchPolicy::isDue($now, 0, $now));
    }

    public function testIsDueWithinLookbackWindow(): void
    {
        $now = new \DateTimeImmutable('2026-08-17 12:00:00', new \DateTimeZone('UTC'));
        $scheduledAt = $now->modify('-3 minutes');

        self::assertTrue(ReminderDispatchPolicy::isDue($scheduledAt, 0, $now));
    }

    public function testIsNotDueOutsideLookbackWindow(): void
    {
        $now = new \DateTimeImmutable('2026-08-17 12:00:00', new \DateTimeZone('UTC'));
        $scheduledAt = $now->modify('-2 hours');

        self::assertFalse(ReminderDispatchPolicy::isDue($scheduledAt, 0, $now));
    }

    public function testIsNotDueBeforeFireAt(): void
    {
        $now = new \DateTimeImmutable('2026-08-17 12:00:00', new \DateTimeZone('UTC'));
        $scheduledAt = $now->modify('+20 minutes');

        self::assertFalse(ReminderDispatchPolicy::isDue($scheduledAt, 0, $now));
        self::assertTrue(ReminderDispatchPolicy::isDue($scheduledAt, 20, $now));
    }

    public function testQueryWindowCoversLookbackAndAnticipation(): void
    {
        $now = new \DateTimeImmutable('2026-08-17 12:00:00', new \DateTimeZone('UTC'));
        [$from, $to] = ReminderDispatchPolicy::queryWindow($now);

        self::assertSame('2026-08-17 11:35:00', $from->format('Y-m-d H:i:s'));
        self::assertSame('2026-08-17 12:15:00', $to->format('Y-m-d H:i:s'));
    }
}
