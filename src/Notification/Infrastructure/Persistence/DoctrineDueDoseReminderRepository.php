<?php

declare(strict_types=1);

namespace Notification\Infrastructure\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use DoseEvent\Infrastructure\Persistence\DoseEventDoctrineEntity;
use Medication\Infrastructure\Persistence\MedicationDoctrineEntity;
use Notification\Domain\DueDoseReminder;
use Notification\Domain\DueDoseReminderRepository;
use Profile\Infrastructure\Persistence\PatientProfileDoctrineEntity;
use Shared\Domain\ValueObject\DoseEventId;
use Shared\Domain\ValueObject\UserId;

final class DoctrineDueDoseReminderRepository implements DueDoseReminderRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    /**
     * @return DueDoseReminder[]
     */
    public function findDueDoseReminders(\DateTimeImmutable $now): array
    {
        // Maximum anticipation supported is 15 minutes. We query all pending un-notified doses up to now + 15 min.
        $maxWindow = $now->modify('+15 minutes');

        $dql = '
            SELECT 
                d.id AS dose_id,
                d.scheduledAt AS scheduled_at,
                m.name AS medication_name,
                m.dosage AS dosage,
                p.accountId AS account_id,
                np.doseRemindersEnabled AS dose_reminders_enabled,
                np.inAppBannersEnabled AS in_app_banners_enabled,
                np.reminderMinutesBefore AS reminder_minutes_before
            FROM ' . DoseEventDoctrineEntity::class . ' d
            JOIN ' . MedicationDoctrineEntity::class . ' m ON d.medicationId = m.id
            JOIN ' . PatientProfileDoctrineEntity::class . ' p ON m.profileId = p.id
            LEFT JOIN ' . NotificationPreferencesDoctrineEntity::class . ' np ON p.accountId = np.accountId
            WHERE d.status = :pendingStatus
              AND d.reminderSentAt IS NULL
              AND d.scheduledAt <= :maxWindow
            ORDER BY d.scheduledAt ASC
        ';

        $query = $this->entityManager->createQuery($dql);
        $query->setParameter('pendingStatus', 'pending');
        $query->setParameter('maxWindow', $maxWindow);

        /** @var array<int, array{
         *     dose_id: string,
         *     scheduled_at: \DateTimeImmutable,
         *     medication_name: string,
         *     dosage: string,
         *     account_id: string,
         *     dose_reminders_enabled: ?bool,
         *     in_app_banners_enabled: ?bool,
         *     reminder_minutes_before: ?int
         * }> $rows */
        $rows = $query->getResult();

        $dueReminders = [];

        foreach ($rows as $row) {
            $minutesBefore = $row['reminder_minutes_before'] ?? 0;
            $fireAt = $row['scheduled_at']->modify(sprintf('-%d minutes', $minutesBefore));

            // Only include if the trigger time has arrived (fireAt <= now)
            if ($fireAt <= $now) {
                $dueReminders[] = new DueDoseReminder(
                    new DoseEventId($row['dose_id']),
                    new UserId($row['account_id']),
                    $row['medication_name'],
                    $row['dosage'],
                    $row['scheduled_at'],
                    $minutesBefore,
                    $row['dose_reminders_enabled'] ?? true,
                    $row['in_app_banners_enabled'] ?? true
                );
            }
        }

        return $dueReminders;
    }
}
