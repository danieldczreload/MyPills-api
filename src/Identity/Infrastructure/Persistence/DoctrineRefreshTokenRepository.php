<?php

declare(strict_types=1);

namespace Identity\Infrastructure\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use Identity\Domain\RefreshToken;
use Identity\Domain\RefreshTokenRepository;
use Identity\Domain\ValueObject\RefreshTokenId;
use Shared\Domain\ValueObject\UserId;

final class DoctrineRefreshTokenRepository implements RefreshTokenRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function save(RefreshToken $token): void
    {
        $entity = new RefreshTokenDoctrineEntity(
            $token->id()->value(),
            $token->accountId()->value(),
            $token->token(),
            $token->expiresAt(),
            $token->createdAt()
        );

        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    public function findByToken(string $token): ?RefreshToken
    {
        $entity = $this->entityManager->getRepository(RefreshTokenDoctrineEntity::class)
            ->findOneBy(['token' => $token]);

        if ($entity === null) {
            return null;
        }

        return new RefreshToken(
            new RefreshTokenId($entity->getId()),
            new UserId($entity->getAccountId()),
            $entity->getToken(),
            $entity->getExpiresAt(),
            $entity->getCreatedAt()
        );
    }

    public function delete(RefreshToken $token): void
    {
        $entity = $this->entityManager->find(RefreshTokenDoctrineEntity::class, $token->id()->value());
        if ($entity !== null) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();
        }
    }
}
