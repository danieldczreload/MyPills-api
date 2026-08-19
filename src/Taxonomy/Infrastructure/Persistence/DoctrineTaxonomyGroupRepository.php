<?php

declare(strict_types=1);

namespace Taxonomy\Infrastructure\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use Shared\Domain\ValueObject\ProfileId;
use Shared\Domain\ValueObject\TaxonomyGroupId;
use Taxonomy\Domain\TaxonomyGroup;
use Taxonomy\Domain\TaxonomyGroupRepository;

final class DoctrineTaxonomyGroupRepository implements TaxonomyGroupRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function save(TaxonomyGroup $group): void
    {
        $entity = $this->entityManager->find(TaxonomyGroupDoctrineEntity::class, $group->id()->value());

        if ($entity === null) {
            $entity = new TaxonomyGroupDoctrineEntity(
                $group->id()->value(),
                $group->profileId()->value(),
                $group->type(),
                $group->name(),
                $group->description(),
                $group->iconName(),
                $group->colorValue(),
                $group->isCustom(),
                $group->clientId(),
                $group->createdAt(),
                $group->updatedAt()
            );
            $this->entityManager->persist($entity);
        } else {
            $entity->setType($group->type());
            $entity->setName($group->name());
            $entity->setDescription($group->description());
            $entity->setIconName($group->iconName());
            $entity->setColorValue($group->colorValue());
            $entity->setIsCustom($group->isCustom());
            $entity->setUpdatedAt($group->updatedAt());
        }

        $this->entityManager->flush();
    }

    public function findById(TaxonomyGroupId $id): ?TaxonomyGroup
    {
        $entity = $this->entityManager->find(TaxonomyGroupDoctrineEntity::class, $id->value());
        if ($entity === null) {
            return null;
        }

        return $this->mapToDomain($entity);
    }

    /**
     * @return TaxonomyGroup[]
     */
    public function findByProfileId(ProfileId $profileId): array
    {
        $entities = $this->entityManager->getRepository(TaxonomyGroupDoctrineEntity::class)
            ->findBy(['profileId' => $profileId->value()]);

        return array_map($this->mapToDomain(...), $entities);
    }

    public function findByClientId(string $clientId): ?TaxonomyGroup
    {
        $entity = $this->entityManager->getRepository(TaxonomyGroupDoctrineEntity::class)
            ->findOneBy(['clientId' => $clientId]);

        if ($entity === null) {
            return null;
        }

        return $this->mapToDomain($entity);
    }

    public function delete(TaxonomyGroup $group): void
    {
        $entity = $this->entityManager->find(TaxonomyGroupDoctrineEntity::class, $group->id()->value());
        if ($entity !== null) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();
        }
    }

    private function mapToDomain(TaxonomyGroupDoctrineEntity $entity): TaxonomyGroup
    {
        return new TaxonomyGroup(
            new TaxonomyGroupId($entity->getId()),
            new ProfileId($entity->getProfileId()),
            $entity->getType(),
            $entity->getName(),
            $entity->getDescription(),
            $entity->getIconName(),
            $entity->getColorValue(),
            $entity->isCustom(),
            $entity->getClientId(),
            $entity->getCreatedAt(),
            $entity->getUpdatedAt()
        );
    }
}
