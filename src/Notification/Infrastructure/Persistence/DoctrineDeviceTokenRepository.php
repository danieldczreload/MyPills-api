<?php

declare(strict_types=1);

namespace Notification\Infrastructure\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use Notification\Domain\DeviceToken;
use Notification\Domain\DeviceTokenRepository;
use Shared\Domain\ValueObject\UserId;

final class DoctrineDeviceTokenRepository implements DeviceTokenRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function save(DeviceToken $deviceToken): void
    {
        $entity = $this->entityManager->find(DeviceTokenDoctrineEntity::class, $deviceToken->id());

        if ($entity === null) {
            $entity = new DeviceTokenDoctrineEntity(
                $deviceToken->id(),
                $deviceToken->accountId()->value(),
                $deviceToken->token(),
                $deviceToken->platform(),
                $deviceToken->locale(),
                $deviceToken->createdAt()
            );
            $this->entityManager->persist($entity);
        }

        $this->entityManager->flush();
    }

    public function findByToken(string $token): ?DeviceToken
    {
        $entity = $this->entityManager->getRepository(DeviceTokenDoctrineEntity::class)
            ->findOneBy(['token' => $token]);

        if ($entity === null) {
            return null;
        }

        return $this->mapToDomain($entity);
    }

    /**
     * @return DeviceToken[]
     */
    public function findByAccountId(UserId $accountId): array
    {
        $entities = $this->entityManager->getRepository(DeviceTokenDoctrineEntity::class)
            ->findBy(['accountId' => $accountId->value()]);

        return array_map($this->mapToDomain(...), $entities);
    }

    public function delete(DeviceToken $deviceToken): void
    {
        $entity = $this->entityManager->find(DeviceTokenDoctrineEntity::class, $deviceToken->id());
        if ($entity !== null) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();
        }
    }

    private function mapToDomain(DeviceTokenDoctrineEntity $entity): DeviceToken
    {
        return new DeviceToken(
            $entity->getId(),
            new UserId($entity->getAccountId()),
            $entity->getToken(),
            $entity->getPlatform(),
            $entity->getLocale(),
            $entity->getCreatedAt()
        );
    }
}
