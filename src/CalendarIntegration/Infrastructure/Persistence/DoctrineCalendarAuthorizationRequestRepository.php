<?php

declare(strict_types=1);

namespace CalendarIntegration\Infrastructure\Persistence;

use CalendarIntegration\Domain\CalendarAuthorizationRequest;
use CalendarIntegration\Domain\CalendarAuthorizationRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Shared\Domain\ValueObject\ProfileId;
use Shared\Domain\ValueObject\UserId;

final class DoctrineCalendarAuthorizationRequestRepository implements CalendarAuthorizationRequestRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function save(CalendarAuthorizationRequest $request): void
    {
        $entity = new CalendarAuthorizationRequestDoctrineEntity(
            $request->id(),
            $request->accountId()->value(),
            $request->profileId()->value(),
            $request->provider(),
            $request->stateHash(),
            $request->codeChallenge(),
            $request->expiresAt(),
            $request->createdAt(),
            $request->usedAt()
        );
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    public function findByStateHash(string $stateHash): ?CalendarAuthorizationRequest
    {
        $entity = $this->entityManager->getRepository(CalendarAuthorizationRequestDoctrineEntity::class)
            ->findOneBy(['stateHash' => $stateHash]);

        if ($entity === null) {
            return null;
        }

        return new CalendarAuthorizationRequest(
            $entity->getId(),
            new UserId($entity->getAccountId()),
            new ProfileId($entity->getProfileId()),
            $entity->getProvider(),
            $entity->getStateHash(),
            $entity->getCodeChallenge(),
            $entity->getExpiresAt(),
            $entity->getCreatedAt(),
            $entity->getUsedAt()
        );
    }

    public function consume(CalendarAuthorizationRequest $request, \DateTimeImmutable $now): bool
    {
        $updated = $this->entityManager->createQueryBuilder()
            ->update(CalendarAuthorizationRequestDoctrineEntity::class, 'authorizationRequest')
            ->set('authorizationRequest.usedAt', ':usedAt')
            ->where('authorizationRequest.id = :id')
            ->andWhere('authorizationRequest.usedAt IS NULL')
            ->andWhere('authorizationRequest.expiresAt > :now')
            ->setParameter('usedAt', $now)
            ->setParameter('id', $request->id())
            ->setParameter('now', $now)
            ->getQuery()
            ->execute();

        return $updated === 1;
    }

    public function deleteExpired(\DateTimeImmutable $now): void
    {
        $this->entityManager->createQueryBuilder()
            ->delete(CalendarAuthorizationRequestDoctrineEntity::class, 'authorizationRequest')
            ->where('authorizationRequest.expiresAt <= :now')
            ->setParameter('now', $now)
            ->getQuery()
            ->execute();
    }
}
