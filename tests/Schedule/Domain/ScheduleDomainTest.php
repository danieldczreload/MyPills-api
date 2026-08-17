<?php

declare(strict_types=1);

namespace App\Tests\Schedule\Domain;

use PHPUnit\Framework\TestCase;
use Schedule\Domain\DailyIntervalSchedule;
use Schedule\Domain\DailySchedule;
use Schedule\Domain\SpecificDaysSchedule;
use Schedule\Domain\ValueObject\TimeOfDay;
use Shared\Domain\ValueObject\MedicationId;
use Shared\Domain\ValueObject\ScheduleId;

final class ScheduleDomainTest extends TestCase
{
    public function testTimeOfDayValidationAndMethods(): void
    {
        $time = new TimeOfDay(14, 30);
        self::assertSame(14, $time->hour());
        self::assertSame(30, $time->minute());
        self::assertSame('14:30', $time->toString());

        $sameTime = TimeOfDay::fromString('14:30');
        self::assertTrue($time->equals($sameTime));

        $diffTime = new TimeOfDay(15, 0);
        self::assertFalse($time->equals($diffTime));
    }

    public function testTimeOfDayInvalidHour(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new TimeOfDay(24, 0);
    }

    public function testTimeOfDayInvalidMinute(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new TimeOfDay(12, 60);
    }

    public function testTimeOfDayFromStringInvalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TimeOfDay::fromString('invalid');
    }

    public function testDailyScheduleProperties(): void
    {
        $id = ScheduleId::generate();
        $medId = MedicationId::generate();
        $start = new \DateTimeImmutable('2026-08-01');
        $schedule = new DailySchedule(
            $id,
            $medId,
            [new TimeOfDay(8, 0)],
            $start,
            null,
            'client-1',
            $start,
            $start
        );

        self::assertTrue($schedule->id()->equals($id));
        self::assertTrue($schedule->medicationId()->equals($medId));
        self::assertSame('daily', $schedule->type());
        self::assertCount(1, $schedule->timesOfDay());
        self::assertSame('client-1', $schedule->clientId());
    }

    public function testDailyIntervalScheduleProperties(): void
    {
        $id = ScheduleId::generate();
        $medId = MedicationId::generate();
        $start = new \DateTimeImmutable('2026-08-01');
        $startAt = new TimeOfDay(8, 0);
        $endAt = new TimeOfDay(20, 0);

        $schedule = new DailyIntervalSchedule(
            $id,
            $medId,
            4,
            $startAt,
            $endAt,
            $start,
            null,
            null,
            $start,
            $start
        );

        self::assertSame(4, $schedule->everyHours());
        self::assertSame($startAt, $schedule->startAt());
        self::assertSame($endAt, $schedule->endAt());
        self::assertSame('daily_interval', $schedule->type());
    }

    public function testDailyIntervalScheduleInvalidEveryHours(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new DailyIntervalSchedule(
            ScheduleId::generate(),
            MedicationId::generate(),
            0,
            new TimeOfDay(8, 0),
            null,
            new \DateTimeImmutable(),
            null,
            null,
            new \DateTimeImmutable(),
            new \DateTimeImmutable()
        );
    }

    public function testSpecificDaysScheduleProperties(): void
    {
        $id = ScheduleId::generate();
        $medId = MedicationId::generate();
        $start = new \DateTimeImmutable('2026-08-01');

        $schedule = new SpecificDaysSchedule(
            $id,
            $medId,
            [1, 3, 5],
            [new TimeOfDay(9, 0)],
            $start,
            null,
            null,
            $start,
            $start
        );

        self::assertSame([1, 3, 5], $schedule->daysOfWeek());
        self::assertCount(1, $schedule->timesOfDay());
        self::assertSame('specific_days', $schedule->type());
    }

    public function testSpecificDaysScheduleInvalidDay(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SpecificDaysSchedule(
            ScheduleId::generate(),
            MedicationId::generate(),
            [8],
            [new TimeOfDay(9, 0)],
            new \DateTimeImmutable(),
            null,
            null,
            new \DateTimeImmutable(),
            new \DateTimeImmutable()
        );
    }
}
