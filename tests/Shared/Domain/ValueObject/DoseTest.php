<?php

declare(strict_types=1);

namespace App\Tests\Shared\Domain\ValueObject;

use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\Dose;
use Shared\Domain\ValueObject\DoseUnit;

final class DoseTest extends TestCase
{
    public function testDisplayFormatsMassAndVolume(): void
    {
        $mg = Dose::of(400, 'mg');
        self::assertSame('400', $mg->amount());
        self::assertSame(400, $mg->amountAsNumber());
        self::assertSame(DoseUnit::Milligram, $mg->unit());
        self::assertSame('400 mg', $mg->display());

        $ml = Dose::of(2.5, 'ml');
        self::assertSame('2.5', $ml->amount());
        self::assertSame(2.5, $ml->amountAsNumber());
        self::assertSame('2.5 ml', $ml->display());
    }

    public function testCountableUnitsPluralize(): void
    {
        self::assertSame('1 tablet', Dose::of(1, 'tablet')->display());
        self::assertSame('2 tablets', Dose::of(2, 'comprimidos')->display());
        self::assertSame('3 drops', Dose::of(3, 'gotas')->display());
    }

    public function testAcceptsAliases(): void
    {
        self::assertSame(DoseUnit::Microgram, Dose::of('0.5', 'µg')->unit());
        self::assertSame(DoseUnit::InternationalUnit, Dose::of(10, 'UI')->unit());
        self::assertSame(DoseUnit::Tablet, Dose::of(1, 'pill')->unit());
    }

    public function testRejectsZeroAndInvalidAmount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Dose::of(0, 'mg');
    }

    public function testRejectsUnknownUnit(): void
    {
        $this->expectException(\ValueError::class);
        Dose::of(1, 'stones');
    }

    public function testCatalogCodesContainCoreUnits(): void
    {
        $codes = DoseUnit::codes();
        self::assertContains('mg', $codes);
        self::assertContains('ml', $codes);
        self::assertContains('mcg', $codes);
        self::assertContains('iu', $codes);
        self::assertContains('tablet', $codes);
    }

    public function testToApiArrayAndPushData(): void
    {
        $dose = Dose::of(400, 'mg');
        self::assertSame(['amount' => 400, 'unit' => 'mg', 'display' => '400 mg'], $dose->toApiArray());
        self::assertSame(
            ['doseDisplay' => '400 mg', 'doseAmount' => '400', 'doseUnit' => 'mg'],
            $dose->toPushData()
        );
        self::assertSame(
            ['doseDisplay' => '', 'doseAmount' => '', 'doseUnit' => ''],
            Dose::emptyPushData()
        );
    }

    public function testTryFromStorage(): void
    {
        $dose = Dose::tryFromStorage('5.0000', 'ml');
        self::assertNotNull($dose);
        self::assertSame('5 ml', $dose->display());
        self::assertNull(Dose::tryFromStorage(null, 'mg'));
        self::assertNull(Dose::tryFromStorage('5', 'nope'));
    }
}
