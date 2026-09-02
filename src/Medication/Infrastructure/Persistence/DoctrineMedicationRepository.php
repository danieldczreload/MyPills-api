<?php

declare(strict_types=1);

namespace Medication\Infrastructure\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use Medication\Domain\Medication;
use Medication\Domain\MedicationRepository;
use Shared\Domain\ValueObject\MedicationId;
use Shared\Domain\ValueObject\ProfileId;

final class DoctrineMedicationRepository implements MedicationRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function save(Medication $medication): void
    {
        $entity = $this->entityManager->find(MedicationDoctrineEntity::class, $medication->id()->value());

        if ($entity === null) {
            $entity = new MedicationDoctrineEntity(
                $medication->id()->value(),
                $medication->profileId()->value(),
                $medication->name(),
                $medication->instructions(),
                $medication->photoUrl(),
                $medication->clientId(),
                $medication->form(),
                $medication->colorToken(),
                $medication->createdAt(),
                $medication->updatedAt()
            );
            $this->entityManager->persist($entity);
        } else {
            $entity->setName($medication->name());
            $entity->setInstructions($medication->instructions());
            $entity->setPhotoUrl($medication->photoUrl());
            $entity->setForm($medication->form());
            $entity->setColorToken($medication->colorToken());
            $entity->setUpdatedAt($medication->updatedAt());
        }

        $this->entityManager->flush();
    }

    public function findById(MedicationId $id): ?Medication
    {
        $entity = $this->entityManager->find(MedicationDoctrineEntity::class, $id->value());
        if ($entity === null) {
            return null;
        }

        return $this->mapToDomain($entity);
    }

    /**
     * @return Medication[]
     */
    public function findByProfileId(ProfileId $profileId): array
    {
        $entities = $this->entityManager->getRepository(MedicationDoctrineEntity::class)
            ->findBy(['profileId' => $profileId->value()]);

        return array_map($this->mapToDomain(...), $entities);
    }

    public function findByClientId(string $clientId): ?Medication
    {
        $entity = $this->entityManager->getRepository(MedicationDoctrineEntity::class)
            ->findOneBy(['clientId' => $clientId]);

        if ($entity === null) {
            return null;
        }

        return $this->mapToDomain($entity);
    }

    public function delete(Medication $medication): void
    {
        $entity = $this->entityManager->find(MedicationDoctrineEntity::class, $medication->id()->value());
        if ($entity !== null) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();
        }
    }

    private function mapToDomain(MedicationDoctrineEntity $entity): Medication
    {
        return new Medication(
            new MedicationId($entity->getId()),
            new ProfileId($entity->getProfileId()),
            $entity->getName(),
            $entity->getInstructions(),
            $entity->getPhotoUrl(),
            $entity->getClientId(),
            $entity->getCreatedAt(),
            $entity->getUpdatedAt(),
            $entity->getForm(),
            $entity->getColorToken()
        );
    }
}
