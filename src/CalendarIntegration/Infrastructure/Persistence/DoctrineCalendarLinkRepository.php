<?php

declare(strict_types=1);

namespace CalendarIntegration\Infrastructure\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use CalendarIntegration\Domain\CalendarLink;
use CalendarIntegration\Domain\CalendarLinkRepository;
use Shared\Domain\ValueObject\ProfileId;

final class DoctrineCalendarLinkRepository implements CalendarLinkRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function save(CalendarLink $link): void
    {
        $entity = $this->entityManager->find(CalendarLinkDoctrineEntity::class, $link->id());

        if ($entity === null) {
            $entity = new CalendarLinkDoctrineEntity(
                $link->id(),
                $link->profileId()->value(),
                $link->provider(),
                $link->refreshToken(),
                $link->createdAt(),
                $link->updatedAt()
            );
            $this->entityManager->persist($entity);
        } else {
            $entity->setRefreshToken($link->refreshToken());
            $entity->setUpdatedAt($link->updatedAt());
        }

        $this->entityManager->flush();
    }

    public function findByProfileAndProvider(ProfileId $profileId, string $provider): ?CalendarLink
    {
        $entity = $this->entityManager->getRepository(CalendarLinkDoctrineEntity::class)
            ->findOneBy(['profileId' => $profileId->value(), 'provider' => $provider]);

        if ($entity === null) {
            return null;
        }

        return $this->mapToDomain($entity);
    }

    /**
     * @return CalendarLink[]
     */
    public function findByProfile(ProfileId $profileId): array
    {
        $entities = $this->entityManager->getRepository(CalendarLinkDoctrineEntity::class)
            ->findBy(['profileId' => $profileId->value()]);

        return array_map($this->mapToDomain(...), $entities);
    }

    public function delete(CalendarLink $link): void
    {
        $entity = $this->entityManager->find(CalendarLinkDoctrineEntity::class, $link->id());
        if ($entity !== null) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();
        }
    }

    private function mapToDomain(CalendarLinkDoctrineEntity $entity): CalendarLink
    {
        return new CalendarLink(
            $entity->getId(),
            new ProfileId($entity->getProfileId()),
            $entity->getProvider(),
            $entity->getRefreshToken(),
            $entity->getCreatedAt(),
            $entity->getUpdatedAt()
        );
    }
}
