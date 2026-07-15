<?php

declare(strict_types=1);

namespace Profile\Infrastructure\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use Profile\Domain\Tombstone;
use Profile\Domain\TombstoneRepository;
use Shared\Domain\ValueObject\ProfileId;

final class DoctrineTombstoneRepository implements TombstoneRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function save(Tombstone $tombstone): void
    {
        $entity = new TombstoneDoctrineEntity(
            $tombstone->id(),
            $tombstone->profileId()->value(),
            $tombstone->entityType(),
            $tombstone->entityId(),
            $tombstone->deletedAt()
        );

        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    /**
     * @return Tombstone[]
     */
    public function findByProfileIdSince(ProfileId $profileId, \DateTimeImmutable $since): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('t')
            ->from(TombstoneDoctrineEntity::class, 't')
            ->where('t.profileId = :profileId')
            ->andWhere('t.deletedAt >= :since')
            ->setParameter('profileId', $profileId->value())
            ->setParameter('since', $since);

        /** @var TombstoneDoctrineEntity[] $entities */
        $entities = $qb->getQuery()->getResult();

        return array_map(static function (TombstoneDoctrineEntity $entity) {
            return new Tombstone(
                $entity->getId(),
                new ProfileId($entity->getProfileId()),
                $entity->getEntityType(),
                $entity->getEntityId(),
                $entity->getDeletedAt()
            );
        }, $entities);
    }
}
