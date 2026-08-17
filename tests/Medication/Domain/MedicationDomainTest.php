<?php

declare(strict_types=1);

namespace App\Tests\Medication\Domain;

use Medication\Domain\Medication;
use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\MedicationId;
use Shared\Domain\ValueObject\ProfileId;

final class MedicationDomainTest extends TestCase
{
    public function testMedicationPropertiesAndLifecycle(): void
    {
        $id = MedicationId::generate();
        $profId = ProfileId::generate();

        $med = Medication::create(
            $id,
            $profId,
            'Amoxicillin',
            '500mg',
            'Every 8 hours',
            'https://example.com/amox.jpg',
            'client-123'
        );

        self::assertTrue($med->id()->equals($id));
        self::assertTrue($med->profileId()->equals($profId));
        self::assertSame('Amoxicillin', $med->name());
        self::assertSame('500mg', $med->dosage());
        self::assertSame('Every 8 hours', $med->instructions());
        self::assertSame('https://example.com/amox.jpg', $med->photoUrl());
        self::assertSame('client-123', $med->clientId());

        $med->update('Amoxicillin Clavulanate', '875mg', 'With meals', null);
        self::assertSame('Amoxicillin Clavulanate', $med->name());
        self::assertSame('875mg', $med->dosage());
        self::assertSame('With meals', $med->instructions());
        self::assertNull($med->photoUrl());
    }
}
