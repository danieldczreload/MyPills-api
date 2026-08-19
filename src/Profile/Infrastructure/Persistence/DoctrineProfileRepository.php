<?php

declare(strict_types=1);

namespace Profile\Infrastructure\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use Profile\Domain\PatientProfile;
use Profile\Domain\ProfileRepository;
use Shared\Domain\ValueObject\ProfileId;
use Shared\Domain\ValueObject\UserId;

final class DoctrineProfileRepository implements ProfileRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function save(PatientProfile $profile): void
    {
        $entity = $this->entityManager->find(PatientProfileDoctrineEntity::class, $profile->id()->value());

        if ($entity === null) {
            $entity = new PatientProfileDoctrineEntity(
                $profile->id()->value(),
                $profile->accountId()->value(),
                $profile->name(),
                $profile->birthDate(),
                $profile->gender(),
                $profile->photoUrl(),
                $profile->timezone(),
                $profile->createdAt(),
                $profile->updatedAt()
            );
            $this->entityManager->persist($entity);
        } else {
            $entity->setName($profile->name());
            $entity->setBirthDate($profile->birthDate());
            $entity->setGender($profile->gender());
            $entity->setPhotoUrl($profile->photoUrl());
            $entity->setTimezone($profile->timezone());
            $entity->setUpdatedAt($profile->updatedAt());
        }

        $this->entityManager->flush();
    }

    public function findById(ProfileId $id): ?PatientProfile
    {
        $entity = $this->entityManager->find(PatientProfileDoctrineEntity::class, $id->value());
        if ($entity === null) {
            return null;
        }

        return $this->mapToDomain($entity);
    }

    /**
     * @return PatientProfile[]
     */
    public function findByAccountId(UserId $accountId): array
    {
        $entities = $this->entityManager->getRepository(PatientProfileDoctrineEntity::class)
            ->findBy(['accountId' => $accountId->value()]);

        return array_map($this->mapToDomain(...), $entities);
    }

    public function delete(PatientProfile $profile): void
    {
        $entity = $this->entityManager->find(PatientProfileDoctrineEntity::class, $profile->id()->value());
        if ($entity !== null) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();
        }
    }

    private function mapToDomain(PatientProfileDoctrineEntity $entity): PatientProfile
    {
        return new PatientProfile(
            new ProfileId($entity->getId()),
            new UserId($entity->getAccountId()),
            $entity->getName(),
            $entity->getBirthDate(),
            $entity->getGender(),
            $entity->getPhotoUrl(),
            $entity->getCreatedAt(),
            $entity->getUpdatedAt(),
            $entity->getTimezone()
        );
    }
}
