<?php

declare(strict_types=1);

namespace CalendarIntegration\Infrastructure\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use CalendarIntegration\Domain\CalendarLink;
use CalendarIntegration\Domain\CalendarLinkRepository;
use CalendarIntegration\Domain\CalendarLinkStatus;
use Shared\Domain\TokenVault;
use Shared\Domain\ValueObject\ProfileId;

final class DoctrineCalendarLinkRepository implements CalendarLinkRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TokenVault $tokenVault
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
                $link->encryptedRefreshToken(),
                $link->createdAt(),
                $link->updatedAt(),
                $link->status()->value
            );
            $this->entityManager->persist($entity);
        } else {
            $entity->setRefreshToken($link->encryptedRefreshToken());
            $entity->setUpdatedAt($link->updatedAt());
            $entity->setStatus($link->status()->value);
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
        $encryptedRefreshToken = $entity->getRefreshToken();
        if (!$this->tokenVault->isEncrypted($encryptedRefreshToken)) {
            throw new \LogicException('Plaintext calendar token found. Run the token encryption migration.');
        }

        return new CalendarLink(
            $entity->getId(),
            new ProfileId($entity->getProfileId()),
            $entity->getProvider(),
            $encryptedRefreshToken,
            $entity->getCreatedAt(),
            $entity->getUpdatedAt(),
            CalendarLinkStatus::tryFrom($entity->getStatus()) ?? CalendarLinkStatus::ACTIVE
        );
    }

}
