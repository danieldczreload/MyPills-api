<?php

declare(strict_types=1);

namespace CalendarIntegration\Infrastructure\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use CalendarIntegration\Domain\CalendarEventMapping;
use CalendarIntegration\Domain\CalendarEventMappingRepository;
use Shared\Domain\ValueObject\ProfileId;

final class DoctrineCalendarEventMappingRepository implements CalendarEventMappingRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function save(CalendarEventMapping $mapping): void
    {
        $entity = $this->entityManager->find(CalendarEventMappingDoctrineEntity::class, $mapping->id());

        if ($entity === null) {
            $entity = new CalendarEventMappingDoctrineEntity(
                $mapping->id(),
                $mapping->doseEventId(),
                $mapping->provider(),
                $mapping->externalEventId(),
                $mapping->createdAt()
            );
            $this->entityManager->persist($entity);
        } else {
            $entity->setExternalEventId($mapping->externalEventId());
        }

        $this->entityManager->flush();
    }

    public function findByDoseEventAndProvider(string $doseEventId, string $provider): ?CalendarEventMapping
    {
        $entity = $this->entityManager->getRepository(CalendarEventMappingDoctrineEntity::class)
            ->findOneBy(['doseEventId' => $doseEventId, 'provider' => $provider]);

        if ($entity === null) {
            return null;
        }

        return $this->mapToDomain($entity);
    }

    /**
     * @return CalendarEventMapping[]
     */
    public function findByProfileAndProvider(ProfileId $profileId, string $provider): array
    {
        $rows = $this->entityManager->getConnection()->fetchAllAssociative(
            <<<'SQL'
            SELECT mapping.id, mapping.dose_event_id, mapping.provider,
                   mapping.external_event_id, mapping.created_at
            FROM calendar_event_mappings mapping
            INNER JOIN dose_events dose_event ON dose_event.id = mapping.dose_event_id
            INNER JOIN medications medication ON medication.id = dose_event.medication_id
            WHERE medication.profile_id = :profileId
              AND mapping.provider = :provider
            SQL,
            [
                'profileId' => $profileId->value(),
                'provider' => $provider,
            ]
        );

        return array_map(static function (array $row): CalendarEventMapping {
            $id = $row['id'] ?? null;
            $doseEventId = $row['dose_event_id'] ?? null;
            $rowProvider = $row['provider'] ?? null;
            $externalEventId = $row['external_event_id'] ?? null;
            $createdAt = $row['created_at'] ?? null;

            if (
                !is_string($id)
                || !is_string($doseEventId)
                || !is_string($rowProvider)
                || !is_string($externalEventId)
                || !is_string($createdAt)
            ) {
                throw new \RuntimeException('Invalid calendar event mapping row.');
            }

            return new CalendarEventMapping(
                $id,
                $doseEventId,
                $rowProvider,
                $externalEventId,
                new \DateTimeImmutable($createdAt)
            );
        }, $rows);
    }

    public function findByDoseEvents(array $doseEventIds, string $provider): array
    {
        if ($doseEventIds === []) {
            return [];
        }

        $entities = $this->entityManager->getRepository(CalendarEventMappingDoctrineEntity::class)
            ->findBy(['doseEventId' => $doseEventIds, 'provider' => $provider]);

        $mappings = [];
        foreach ($entities as $entity) {
            $mappings[$entity->getDoseEventId() . ':' . $entity->getProvider()] = $this->mapToDomain($entity);
        }

        return $mappings;
    }

    public function flush(): void
    {
        $this->entityManager->flush();
    }

    private function mapToDomain(CalendarEventMappingDoctrineEntity $entity): CalendarEventMapping
    {
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
