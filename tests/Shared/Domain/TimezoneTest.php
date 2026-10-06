<?php

declare(strict_types=1);

namespace App\Tests\Shared\Domain;

use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\Timezone;

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

    public function testAcceptsPosixGmtOffsetAndRejectsLegacyAliases(): void
    {
        $timezone = new Timezone('Etc/GMT+6');

        self::assertSame('Etc/GMT+6', $timezone->value());
        self::assertSame(-21600, $timezone->toDateTimeZone()->getOffset(new \DateTimeImmutable('2026-10-06')));
        self::assertNull(Timezone::tryParse('US/Central'));
        self::assertNull(Timezone::tryParse('Etc/GMT+99'));
    }
}
