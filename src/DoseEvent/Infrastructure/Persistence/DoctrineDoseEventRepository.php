<?php

declare(strict_types=1);

namespace DoseEvent\Infrastructure\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use DoseEvent\Domain\DoseEvent;
use DoseEvent\Domain\DoseEventRepository;
use Shared\Domain\ValueObject\DoseEventId;
use Shared\Domain\ValueObject\MedicationId;
use Shared\Domain\ValueObject\ScheduleId;

final class DoctrineDoseEventRepository implements DoseEventRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function save(DoseEvent $doseEvent): void
    {
        $entity = $this->entityManager->find(DoseEventDoctrineEntity::class, $doseEvent->id()->value());

        if ($entity === null) {
            $entity = new DoseEventDoctrineEntity(
                $doseEvent->id()->value(),
                $doseEvent->medicationId()->value(),
                $doseEvent->scheduleId()->value(),
                $doseEvent->scheduledAt(),
                $doseEvent->status(),
                $doseEvent->takenAt(),
                $doseEvent->clientId(),
                $doseEvent->createdAt(),
                $doseEvent->updatedAt()
            );
            $this->entityManager->persist($entity);
        } else {
            $entity->setStatus($doseEvent->status());
            $entity->setTakenAt($doseEvent->takenAt());
            $entity->setUpdatedAt($doseEvent->updatedAt());
        }

        $this->entityManager->flush();
    }

    public function findById(DoseEventId $id): ?DoseEvent
    {
        $entity = $this->entityManager->find(DoseEventDoctrineEntity::class, $id->value());
        if ($entity === null) {
            return null;
        }

        return $this->mapToDomain($entity);
    }

    /**
     * @return DoseEvent[]
     */
    public function findByScheduleId(ScheduleId $scheduleId): array
    {
        $entities = $this->entityManager->getRepository(DoseEventDoctrineEntity::class)
            ->findBy(['scheduleId' => $scheduleId->value()]);

        return array_map($this->mapToDomain(...), $entities);
    }

    /**
     * @param ScheduleId[] $scheduleIds
     * @return DoseEvent[]
     */
    public function findByScheduleIds(array $scheduleIds): array
    {
        if (count($scheduleIds) === 0) {
            return [];
        }

        $ids = array_map(static fn (ScheduleId $id) => $id->value(), $scheduleIds);

        $entities = $this->entityManager->getRepository(DoseEventDoctrineEntity::class)
            ->findBy(['scheduleId' => $ids]);

        return array_map($this->mapToDomain(...), $entities);
    }

    /**
     * @param ScheduleId[] $scheduleIds
     * @return DoseEvent[]
     */
    public function findPendingByScheduleIds(array $scheduleIds): array
    {
        if (count($scheduleIds) === 0) {
            return [];
        }

        $ids = array_map(static fn (ScheduleId $id) => $id->value(), $scheduleIds);

        $entities = $this->entityManager->getRepository(DoseEventDoctrineEntity::class)
            ->findBy(['scheduleId' => $ids, 'status' => 'pending']);

        return array_map($this->mapToDomain(...), $entities);
    }

    /**
     * @param ScheduleId[] $scheduleIds
     */
    public function deletePendingByScheduleIds(array $scheduleIds): void
    {
        if (count($scheduleIds) === 0) {
            return;
        }

        $ids = array_map(static fn (ScheduleId $id) => $id->value(), $scheduleIds);

        $qb = $this->entityManager->createQueryBuilder();
        $qb->delete(DoseEventDoctrineEntity::class, 'd')
            ->where('d.scheduleId IN (:ids)')
            ->andWhere('d.status = :status')
            ->setParameter('ids', $ids)
            ->setParameter('status', 'pending');

        $qb->getQuery()->execute();
    }

    public function findByClientId(string $clientId): ?DoseEvent
    {
        $entity = $this->entityManager->getRepository(DoseEventDoctrineEntity::class)
            ->findOneBy(['clientId' => $clientId]);

        if ($entity === null) {
            return null;
        }

        return $this->mapToDomain($entity);
    }

    /**
     * @param ScheduleId[] $scheduleIds
     * @return DoseEvent[]
     */
    public function findByScheduleIdsAndRange(array $scheduleIds, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        if (count($scheduleIds) === 0) {
            return [];
        }

        $ids = array_map(static fn (ScheduleId $id) => $id->value(), $scheduleIds);

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('d')
            ->from(DoseEventDoctrineEntity::class, 'd')
            ->where('d.scheduleId IN (:ids)')
            ->andWhere('d.scheduledAt >= :from')
            ->andWhere('d.scheduledAt <= :to')
            ->setParameter('ids', $ids)
            ->setParameter('from', $from)
            ->setParameter('to', $to);

        /** @var DoseEventDoctrineEntity[] $entities */
        $entities = $qb->getQuery()->getResult();

        return array_map($this->mapToDomain(...), $entities);
    }

    public function delete(DoseEvent $doseEvent): void
    {
        $entity = $this->entityManager->find(DoseEventDoctrineEntity::class, $doseEvent->id()->value());
        if ($entity !== null) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();
        }
    }

    private function mapToDomain(DoseEventDoctrineEntity $entity): DoseEvent
    {
        return new DoseEvent(
            new DoseEventId($entity->getId()),
            new MedicationId($entity->getMedicationId()),
            new ScheduleId($entity->getScheduleId()),
            $entity->getScheduledAt(),
            $entity->getStatus(),
            $entity->getTakenAt(),
            $entity->getClientId(),
            $entity->getCreatedAt(),
            $entity->getUpdatedAt()
        );
    }
}
