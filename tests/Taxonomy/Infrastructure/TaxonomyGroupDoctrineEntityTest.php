<?php

declare(strict_types=1);

namespace App\Tests\Taxonomy\Infrastructure;

use PHPUnit\Framework\TestCase;
use Taxonomy\Infrastructure\Persistence\TaxonomyGroupDoctrineEntity;

final class TaxonomyGroupDoctrineEntityTest extends TestCase
{
    public function testEntityGettersAndSetters(): void
    {
        $createdAt = new \DateTimeImmutable('2026-01-01T00:00:00Z');
        $updatedAt = new \DateTimeImmutable('2026-01-01T00:00:00Z');

        $entity = new TaxonomyGroupDoctrineEntity(
            'tax-1',
            'prof-1',
            'category',
            'Vitamins',
            'Daily vitamins',
            'icon_vit',
            0x123456,
            true,
            'client-1',
            $createdAt,
            $updatedAt
        );

        self::assertSame('tax-1', $entity->getId());
        self::assertSame('prof-1', $entity->getProfileId());
        self::assertSame('category', $entity->getType());
        self::assertSame('Vitamins', $entity->getName());
        self::assertSame('Daily vitamins', $entity->getDescription());
        self::assertSame('icon_vit', $entity->getIconName());
        self::assertSame(0x123456, $entity->getColorValue());
        self::assertTrue($entity->isCustom());
        self::assertSame('client-1', $entity->getClientId());
        self::assertSame($createdAt, $entity->getCreatedAt());
        self::assertSame($updatedAt, $entity->getUpdatedAt());

        $newUpdated = new \DateTimeImmutable('2026-01-02T00:00:00Z');
        $entity->setType('tag');
        $entity->setName('Supplements');
        $entity->setDescription('Herbal');
        $entity->setIconName('leaf');
        $entity->setColorValue(0x654321);
        $entity->setIsCustom(false);
        $entity->setUpdatedAt($newUpdated);

        self::assertSame('tag', $entity->getType());
        self::assertSame('Supplements', $entity->getName());
        self::assertSame('Herbal', $entity->getDescription());
        self::assertSame('leaf', $entity->getIconName());
        self::assertSame(0x654321, $entity->getColorValue());
        self::assertFalse($entity->isCustom());
        self::assertSame($newUpdated, $entity->getUpdatedAt());
    }
}
