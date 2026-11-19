<?php

declare(strict_types=1);

namespace App\Tests\Schedule\Domain;

use PHPUnit\Framework\TestCase;
use Schedule\Domain\DailyIntervalSchedule;
use Schedule\Domain\DailySchedule;
use Schedule\Domain\SpecificDaysSchedule;
use Schedule\Domain\ValueObject\TimeOfDay;
use Shared\Domain\ValueObject\Dose;
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
            $start,
            Dose::of(500, 'mg')
        );

        self::assertTrue($schedule->id()->equals($id));
        self::assertTrue($schedule->medicationId()->equals($medId));
        self::assertSame('daily', $schedule->type());
        self::assertCount(1, $schedule->timesOfDay());
        self::assertSame('client-1', $schedule->clientId());
        self::assertSame('500 mg', $schedule->dose()?->display());
        self::assertTrue($schedule->notifyAlarm());
        self::assertFalse($schedule->notifyPush());
        self::assertFalse($schedule->notifyCalendar());
    }

    public function testNotificationSwitchesDefaultsAndOverrides(): void
    {
        $id = ScheduleId::generate();
        $medId = MedicationId::generate();
        $start = new \DateTimeImmutable('2026-08-01');

        $defaultSchedule = new DailySchedule(
            $id,
            $medId,
            [new TimeOfDay(8, 0)],
            $start,
            null,
            null,
            $start,
            $start
        );
        self::assertTrue($defaultSchedule->notifyAlarm());
        self::assertFalse($defaultSchedule->notifyPush());
        self::assertFalse($defaultSchedule->notifyCalendar());

        $customDaily = new DailySchedule(
            $id,
            $medId,
            [new TimeOfDay(8, 0)],
            $start,
            null,
            null,
            $start,
            $start,
            notifyAlarm: false,
            notifyPush: true,
            notifyCalendar: true
        );
        self::assertFalse($customDaily->notifyAlarm());
        self::assertTrue($customDaily->notifyPush());
        self::assertTrue($customDaily->notifyCalendar());

        $customInterval = new DailyIntervalSchedule(
            $id,
            $medId,
            6,
            new TimeOfDay(8, 0),
            null,
            $start,
            null,
            null,
            $start,
            $start,
            notifyAlarm: false,
            notifyPush: true,
            notifyCalendar: true
        );
        self::assertFalse($customInterval->notifyAlarm());
        self::assertTrue($customInterval->notifyPush());
        self::assertTrue($customInterval->notifyCalendar());

        $customSpecific = new SpecificDaysSchedule(
            $id,
            $medId,
            [1, 2],
            [new TimeOfDay(8, 0)],
            $start,
            null,
            null,
            $start,
            $start,
            notifyAlarm: false,
            notifyPush: true,
            notifyCalendar: true
        );
        self::assertFalse($customSpecific->notifyAlarm());
        self::assertTrue($customSpecific->notifyPush());
        self::assertTrue($customSpecific->notifyCalendar());

        $updatedAt = $start->modify('+2 hours');
        $changed = $customDaily->disableNotificationChannels(false, true, false, $updatedAt);
        self::assertTrue($changed);
        self::assertFalse($customDaily->notifyAlarm());
        self::assertFalse($customDaily->notifyPush());
        self::assertTrue($customDaily->notifyCalendar());
        self::assertSame($updatedAt, $customDaily->updatedAt());

        $noOpAt = $start->modify('+3 hours');
        self::assertFalse($customDaily->disableNotificationChannels(true, true, false, $noOpAt));
        self::assertSame($updatedAt, $customDaily->updatedAt());

        $cancelledAt = $start->modify('+4 hours');
        $customDaily->markCancelled($cancelledAt);
        self::assertTrue($customDaily->isCancelled());
        self::assertSame($cancelledAt, $customDaily->updatedAt());
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

    public function testPinnedTimezoneIgnoresProfileZone(): void
    {
        $schedule = $this->dailySchedule('America/El_Salvador');

        self::assertFalse($schedule->followsProfileTimezone());
        self::assertSame('America/El_Salvador', $schedule->zone(new \DateTimeZone('UTC'))->getName());
        self::assertSame('Etc/GMT+6', $this->dailySchedule('Etc/GMT+6')->zone(new \DateTimeZone('UTC'))->getName());
    }

    public function testBlankTimezoneFollowsProfileZone(): void
    {
        $profileZone = new \DateTimeZone('America/Chicago');

        self::assertNull($this->dailySchedule(null)->timezone());
        self::assertTrue($this->dailySchedule(null)->followsProfileTimezone());
        self::assertSame('America/Chicago', $this->dailySchedule(null)->zone($profileZone)->getName());
        $blank = $this->dailySchedule('');
        self::assertNull($blank->timezone());
        self::assertTrue($blank->followsProfileTimezone());
        self::assertSame('America/Chicago', $blank->zone($profileZone)->getName());
    }

    public function testInvalidPinnedTimezoneFailsAtConstruction(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Timezone "CST" is not a valid IANA identifier.');

        $this->dailySchedule('CST');
    }

    private function dailySchedule(?string $timezone): DailySchedule
    {
        return new DailySchedule(
            ScheduleId::generate(),
            MedicationId::generate(),
            [new TimeOfDay(8, 0)],
            new \DateTimeImmutable('2026-10-06'),
            null,
            null,
            new \DateTimeImmutable('2026-10-06'),
            new \DateTimeImmutable('2026-10-06'),
            null,
            null,
            $timezone
        );
    }
}
