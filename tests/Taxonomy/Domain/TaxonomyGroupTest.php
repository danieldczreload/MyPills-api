<?php

declare(strict_types=1);

namespace App\Tests\Taxonomy\Domain;

use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\ProfileId;
use Shared\Domain\ValueObject\TaxonomyGroupId;
use Taxonomy\Domain\TaxonomyGroup;

final class TaxonomyGroupTest extends TestCase
{
    public function testCreateTaxonomyGroup(): void
    {
        $id = TaxonomyGroupId::generate();
        $profileId = new ProfileId('profile-123');

        $group = TaxonomyGroup::create(
            $id,
            $profileId,
            'category',
            'Cardiovascular',
            'Medications for heart',
            'heart_icon',
            4282562477,
            true,
            'client-123'
        );

        self::assertTrue($group->id()->equals($id));
        self::assertTrue($group->profileId()->equals($profileId));
        self::assertSame('category', $group->type());
        self::assertSame('Cardiovascular', $group->name());
        self::assertSame('Medications for heart', $group->description());
        self::assertSame('heart_icon', $group->iconName());
        self::assertSame(4282562477, $group->colorValue());
        self::assertTrue($group->isCustom());
        self::assertSame('client-123', $group->clientId());
    }

    public function testUpdateTaxonomyGroup(): void
    {
        $group = TaxonomyGroup::create(
            TaxonomyGroupId::generate(),
            new ProfileId('profile-123'),
            'category',
            'Old Name'
        );

        $group->update('tag', 'New Name', 'New Desc', 'star_icon', 123456, false);

        self::assertSame('tag', $group->type());
        self::assertSame('New Name', $group->name());
        self::assertSame('New Desc', $group->description());
        self::assertSame('star_icon', $group->iconName());
        self::assertSame(123456, $group->colorValue());
        self::assertFalse($group->isCustom());
    }
}
