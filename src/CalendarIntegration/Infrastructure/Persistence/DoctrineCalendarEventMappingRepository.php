<?php

declare(strict_types=1);

namespace CalendarIntegration\Infrastructure\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use CalendarIntegration\Domain\CalendarEventMapping;
use CalendarIntegration\Domain\CalendarEventMappingRepository;

final class DoctrineCalendarEventMappingRepository implements CalendarEventMappingRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function save(CalendarEventMapping $mapping): void
    {
        $entity = new CalendarEventMappingDoctrineEntity(
            $mapping->id(),
            $mapping->doseEventId(),
            $mapping->provider(),
            $mapping->externalEventId(),
            $mapping->createdAt()
        );

        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    public function findByDoseEventAndProvider(string $doseEventId, string $provider): ?CalendarEventMapping
    {
        $entity = $this->entityManager->getRepository(CalendarEventMappingDoctrineEntity::class)
            ->findOneBy(['doseEventId' => $doseEventId, 'provider' => $provider]);

        if ($entity === null) {
            return null;
        }

        return new CalendarEventMapping(
            $entity->getId(),
            $entity->getDoseEventId(),
            $entity->getProvider(),
            $entity->getExternalEventId(),
            $entity->getCreatedAt()
        );
    }

    public function delete(CalendarEventMapping $mapping): void
    {
        $entity = $this->entityManager->find(CalendarEventMappingDoctrineEntity::class, $mapping->id());
        if ($entity !== null) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();
        }
    }
}
