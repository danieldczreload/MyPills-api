<?php

declare(strict_types=1);

namespace App\Tests\Shared\Domain;

use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\DoseEventId;
use Shared\Domain\ValueObject\MedicationId;
use Shared\Domain\ValueObject\ScheduleId;

final class ValueObjectsFullTest extends TestCase
{
    public function testDoseEventId(): void
    {
        $id = DoseEventId::generate();
        self::assertNotEmpty($id->value());
        self::assertSame($id->value(), (string) $id);

        $same = new DoseEventId($id->value());
        self::assertTrue($id->equals($same));

        $diff = DoseEventId::generate();
        self::assertFalse($id->equals($diff));
    }

    public function testDoseEventIdEmptyThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new DoseEventId('');
    }

    public function testMedicationId(): void
    {
        $id = MedicationId::generate();
        self::assertNotEmpty($id->value());
        self::assertSame($id->value(), (string) $id);

        $same = new MedicationId($id->value());
        self::assertTrue($id->equals($same));

        $diff = MedicationId::generate();
        self::assertFalse($id->equals($diff));
    }

    public function testMedicationIdEmptyThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new MedicationId('');
    }

    public function testScheduleId(): void
    {
        $id = ScheduleId::generate();
        self::assertNotEmpty($id->value());
        self::assertSame($id->value(), (string) $id);

        $same = new ScheduleId($id->value());
        self::assertTrue($id->equals($same));

        $diff = ScheduleId::generate();
        self::assertFalse($id->equals($diff));
    }

    public function testScheduleIdEmptyThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ScheduleId('');
    }
}
