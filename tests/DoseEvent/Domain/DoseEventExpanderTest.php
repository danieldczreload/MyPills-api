<?php

declare(strict_types=1);

namespace App\Tests\DoseEvent\Domain;

use DoseEvent\Domain\DoseEventExpander;
use PHPUnit\Framework\TestCase;
use Schedule\Domain\DailyIntervalSchedule;
use Schedule\Domain\DailySchedule;
use Schedule\Domain\SpecificDaysSchedule;
use Schedule\Domain\ValueObject\TimeOfDay;
use Shared\Domain\ValueObject\MedicationId;
use Shared\Domain\ValueObject\ScheduleId;

final class DoseEventExpanderTest extends TestCase
{
    private DoseEventExpander $expander;
    private MedicationId $medicationId;
    private ScheduleId $scheduleId;
    private \DateTimeZone $utc;

    protected function setUp(): void
    {
        $this->expander = new DoseEventExpander();
        $this->medicationId = MedicationId::generate();
        $this->scheduleId = ScheduleId::generate();
        $this->utc = new \DateTimeZone('UTC');
    }

    public function testExpandDailySchedule(): void
    {
        $startDate = new \DateTimeImmutable('2026-08-01 00:00:00', $this->utc);
        $endDate = new \DateTimeImmutable('2026-08-03 23:59:59', $this->utc);
        $schedule = new DailySchedule(
            $this->scheduleId,
            $this->medicationId,
            [new TimeOfDay(8, 0), new TimeOfDay(20, 0)],
            $startDate,
            $endDate,
            'client-1',
            $startDate,
            $startDate
        );

        $from = new \DateTimeImmutable('2026-08-01 00:00:00', $this->utc);
        $to = new \DateTimeImmutable('2026-08-02 23:59:59', $this->utc);

        $events = $this->expander->expand($schedule, $from, $to, $this->utc);

        self::assertCount(4, $events);
        self::assertSame('08:00', $events[0]->scheduledAt()->format('H:i'));
        self::assertSame('20:00', $events[1]->scheduledAt()->format('H:i'));
        self::assertSame('08:00', $events[2]->scheduledAt()->format('H:i'));
        self::assertSame('20:00', $events[3]->scheduledAt()->format('H:i'));
    }

    public function testExpandDailyIntervalSchedule(): void
    {
        $startDate = new \DateTimeImmutable('2026-08-01 00:00:00', $this->utc);
        $schedule = new DailyIntervalSchedule(
            $this->scheduleId,
            $this->medicationId,
            4,
            new TimeOfDay(8, 0),
            new TimeOfDay(16, 0),
            $startDate,
            null,
            'client-2',
            $startDate,
            $startDate
        );

        $from = new \DateTimeImmutable('2026-08-01 00:00:00', $this->utc);
        $to = new \DateTimeImmutable('2026-08-01 23:59:59', $this->utc);

        $events = $this->expander->expand($schedule, $from, $to, $this->utc);

        // 08:00, 12:00, 16:00 -> 3 events
        self::assertCount(3, $events);
        self::assertSame('08:00', $events[0]->scheduledAt()->format('H:i'));
        self::assertSame('12:00', $events[1]->scheduledAt()->format('H:i'));
        self::assertSame('16:00', $events[2]->scheduledAt()->format('H:i'));
    }

    public function testExpandDailyIntervalScheduleSpanningMidnight(): void
    {
        $startDate = new \DateTimeImmutable('2026-08-01 00:00:00', $this->utc);
        $schedule = new DailyIntervalSchedule(
            $this->scheduleId,
            $this->medicationId,
            6,
            new TimeOfDay(22, 0),
            new TimeOfDay(4, 0), // end earlier than start -> spans next day
            $startDate,
            null,
            'client-3',
            $startDate,
            $startDate
        );

        $from = new \DateTimeImmutable('2026-08-01 00:00:00', $this->utc);
        $to = new \DateTimeImmutable('2026-08-02 12:00:00', $this->utc);

        $events = $this->expander->expand($schedule, $from, $to, $this->utc);
        self::assertNotEmpty($events);
    }

    public function testExpandDailyIntervalScheduleWithNoEndAt(): void
    {
        $startDate = new \DateTimeImmutable('2026-08-01 00:00:00', $this->utc);
        $schedule = new DailyIntervalSchedule(
            $this->scheduleId,
            $this->medicationId,
            8,
            new TimeOfDay(8, 0),
            null,
            $startDate,
            null,
            null,
            $startDate,
            $startDate
        );

        $from = new \DateTimeImmutable('2026-08-01 00:00:00', $this->utc);
        $to = new \DateTimeImmutable('2026-08-01 23:59:59', $this->utc);

        $events = $this->expander->expand($schedule, $from, $to, $this->utc);
        // 08:00, 16:00
        self::assertCount(2, $events);
    }

    public function testExpandSpecificDaysSchedule(): void
    {
        // 2026-08-03 is Monday (1), 2026-08-04 is Tuesday (2), 2026-08-05 is Wednesday (3)
        $startDate = new \DateTimeImmutable('2026-08-03 00:00:00', $this->utc);
        $schedule = new SpecificDaysSchedule(
            $this->scheduleId,
            $this->medicationId,
            [1, 3], // Monday and Wednesday
            [new TimeOfDay(9, 0)],
            $startDate,
            null,
            'client-4',
            $startDate,
            $startDate
        );

        $from = new \DateTimeImmutable('2026-08-03 00:00:00', $this->utc);
        $to = new \DateTimeImmutable('2026-08-05 23:59:59', $this->utc);

        $events = $this->expander->expand($schedule, $from, $to, $this->utc);

        self::assertCount(2, $events);
        self::assertSame('2026-08-03 09:00', $events[0]->scheduledAt()->format('Y-m-d H:i'));
        self::assertSame('2026-08-05 09:00', $events[1]->scheduledAt()->format('Y-m-d H:i'));
    }

    public function testExpandDailyScheduleInProfileTimezoneWithInclusiveEndDate(): void
    {
        $tz = new \DateTimeZone('America/El_Salvador');
        $startDate = new \DateTimeImmutable('2026-08-28 00:00:00', $tz);
        $endDate = new \DateTimeImmutable('2026-08-30 00:00:00', $tz);
        $schedule = new DailySchedule(
            $this->scheduleId,
            $this->medicationId,
            [new TimeOfDay(16, 25)],
            $startDate,
            $endDate,
            'client-sv',
            $startDate,
            $startDate
        );

        $from = new \DateTimeImmutable('2026-08-28 00:00:00', $tz);
        $to = new \DateTimeImmutable('2026-08-30 23:59:59', $tz);

        $events = $this->expander->expand($schedule, $from, $to, $tz);

        self::assertCount(3, $events);
        self::assertSame('2026-08-28', $events[0]->scheduledAt()->setTimezone($tz)->format('Y-m-d'));
        self::assertSame('2026-08-29', $events[1]->scheduledAt()->setTimezone($tz)->format('Y-m-d'));
        self::assertSame('2026-08-30', $events[2]->scheduledAt()->setTimezone($tz)->format('Y-m-d'));
        foreach ($events as $event) {
            self::assertSame('16:25', $event->scheduledAt()->setTimezone($tz)->format('H:i'));
            self::assertSame('22:25', $event->scheduledAt()->setTimezone($this->utc)->format('H:i'));
        }
    }
}
