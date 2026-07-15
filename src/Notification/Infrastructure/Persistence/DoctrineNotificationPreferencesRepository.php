<?php

declare(strict_types=1);

namespace Notification\Infrastructure\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use Notification\Domain\NotificationPreferences;
use Notification\Domain\NotificationPreferencesRepository;
use Shared\Domain\ValueObject\UserId;

final class DoctrineNotificationPreferencesRepository implements NotificationPreferencesRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function save(NotificationPreferences $preferences): void
    {
        $entity = $this->entityManager->find(NotificationPreferencesDoctrineEntity::class, $preferences->id());

        if ($entity === null) {
            $entity = new NotificationPreferencesDoctrineEntity(
                $preferences->id(),
                $preferences->accountId()->value(),
                $preferences->doseRemindersEnabled(),
                $preferences->missedDoseNudgesEnabled(),
                $preferences->refillAlertsEnabled(),
                $preferences->weeklyStreakSummariesEnabled(),
                $preferences->createdAt(),
                $preferences->updatedAt()
            );
            $this->entityManager->persist($entity);
        } else {
            $entity->setDoseRemindersEnabled($preferences->doseRemindersEnabled());
            $entity->setMissedDoseNudgesEnabled($preferences->missedDoseNudgesEnabled());
            $entity->setRefillAlertsEnabled($preferences->refillAlertsEnabled());
            $entity->setWeeklyStreakSummariesEnabled($preferences->weeklyStreakSummariesEnabled());
            $entity->setUpdatedAt($preferences->updatedAt());
        }

        $this->entityManager->flush();
    }

    public function findByAccountId(UserId $accountId): ?NotificationPreferences
    {
        $entity = $this->entityManager->getRepository(NotificationPreferencesDoctrineEntity::class)
            ->findOneBy(['accountId' => $accountId->value()]);

        if ($entity === null) {
            return null;
        }

        return new NotificationPreferences(
            $entity->getId(),
            new UserId($entity->getAccountId()),
            $entity->isDoseRemindersEnabled(),
            $entity->isMissedDoseNudgesEnabled(),
            $entity->isRefillAlertsEnabled(),
            $entity->isWeeklyStreakSummariesEnabled(),
            $entity->getCreatedAt(),
            $entity->getUpdatedAt()
        );
    }
}
