<?php

declare(strict_types=1);

namespace Identity\Infrastructure\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use Identity\Domain\Account;
use Identity\Domain\AccountOAuthLink;
use Identity\Domain\AccountRepository;
use Shared\Domain\ValueObject\Email;
use Shared\Domain\ValueObject\UserId;

final class DoctrineAccountRepository implements AccountRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function save(Account $account): void
    {
        $entity = $this->entityManager->find(AccountDoctrineEntity::class, $account->id()->value());

        if ($entity === null) {
            $entity = new AccountDoctrineEntity(
                $account->id()->value(),
                $account->email()->value(),
                $account->createdAt(),
                $account->updatedAt()
            );
            $this->entityManager->persist($entity);
        } else {
            $entity->setEmail($account->email()->value());
            $entity->setUpdatedAt($account->updatedAt());
        }

        $this->entityManager->flush();
    }

    public function findById(UserId $id): ?Account
    {
        $entity = $this->entityManager->find(AccountDoctrineEntity::class, $id->value());
        if ($entity === null) {
            return null;
        }

        return $this->mapToDomain($entity);
    }

    public function findByEmail(Email $email): ?Account
    {
        $entity = $this->entityManager->getRepository(AccountDoctrineEntity::class)
            ->findOneBy(['email' => $email->value()]);
        if ($entity === null) {
            return null;
        }

        return $this->mapToDomain($entity);
    }

    public function findLinked(string $provider, string $externalId): ?Account
    {
        $link = $this->entityManager->getRepository(AccountOAuthLinkDoctrineEntity::class)
            ->findOneBy(['provider' => $provider, 'externalId' => $externalId]);

        if ($link === null) {
            return null;
        }

        return $this->findById(new UserId($link->getAccountId()));
    }

    public function saveLink(AccountOAuthLink $link): void
    {
        $entity = new AccountOAuthLinkDoctrineEntity(
            $link->id()->value(),
            $link->accountId()->value(),
            $link->provider(),
            $link->externalId(),
            $link->createdAt()
        );

        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    private function mapToDomain(AccountDoctrineEntity $entity): Account
    {
        return new Account(
            new UserId($entity->getId()),
            new Email($entity->getEmail()),
            $entity->getCreatedAt(),
            $entity->getUpdatedAt()
        );
    }
}
