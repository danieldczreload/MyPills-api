<?php

declare(strict_types=1);

namespace App\Tests\Profile\Domain;

use PHPUnit\Framework\TestCase;
use Profile\Domain\ValueObject\Timezone;

final class TimezoneTest extends TestCase
{
    public function testAcceptsIanaIdentifier(): void
    {
        $timezone = new Timezone('America/El_Salvador');

        self::assertSame('America/El_Salvador', $timezone->value());
        self::assertSame('America/El_Salvador', $timezone->toDateTimeZone()->getName());
    }

    public function testRejectsAbbreviation(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Timezone "CST" is not a valid IANA identifier.');

        new Timezone('CST');
    }

    public function testTryParseReturnsNullForInvalidIdentifier(): void
    {
        self::assertNull(Timezone::tryParse('CST'));
        self::assertNull(Timezone::tryParse('GMT-6'));
        self::assertInstanceOf(Timezone::class, Timezone::tryParse('UTC'));
    }

    public function testDateTimeZoneOrUtcFallsBackForInvalidIdentifier(): void
    {
        self::assertSame('UTC', Timezone::dateTimeZoneOrUtc('CST')->getName());
        self::assertSame('America/El_Salvador', Timezone::dateTimeZoneOrUtc('America/El_Salvador')->getName());
    }

    public function testAnchorsCalendarDateToLocalMidnightAndEndOfDay(): void
    {
        $timezone = new Timezone('America/El_Salvador');
        $date = new \DateTimeImmutable('2026-08-30');

        $start = $timezone->startOfDay($date);
        $end = $timezone->endOfDay($date);

        self::assertSame('America/El_Salvador', $start->getTimezone()->getName());
        self::assertSame('2026-08-30 00:00:00', $start->format('Y-m-d H:i:s'));
        self::assertSame('2026-08-30 23:59:59', $end->format('Y-m-d H:i:s'));
        self::assertSame('06:00:00', $start->setTimezone(new \DateTimeZone('UTC'))->format('H:i:s'));
    }
}
