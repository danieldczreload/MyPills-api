<?php

declare(strict_types=1);

namespace Notification\Infrastructure\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use DoseEvent\Infrastructure\Persistence\DoseEventDoctrineEntity;
use Medication\Infrastructure\Persistence\MedicationDoctrineEntity;
use Notification\Domain\DueDoseReminder;
use Notification\Domain\DueDoseReminderRepository;
use Notification\Domain\ReminderDispatchPolicy;
use Profile\Infrastructure\Persistence\PatientProfileDoctrineEntity;
use Schedule\Infrastructure\Persistence\ScheduleDoctrineEntity;
use Shared\Domain\ValueObject\Dose;
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
        [$minWindow, $maxWindow] = ReminderDispatchPolicy::queryWindow($now);

        $dql = '
            SELECT 
                d.id AS dose_id,
                d.scheduledAt AS scheduled_at,
                m.name AS medication_name,
                s.doseAmount AS schedule_dose_amount,
                s.doseUnit AS schedule_dose_unit,
                p.accountId AS account_id,
                np.doseRemindersEnabled AS dose_reminders_enabled,
                np.inAppBannersEnabled AS in_app_banners_enabled,
                np.reminderMinutesBefore AS reminder_minutes_before
            FROM ' . DoseEventDoctrineEntity::class . ' d
            JOIN ' . MedicationDoctrineEntity::class . ' m ON d.medicationId = m.id
            JOIN ' . ScheduleDoctrineEntity::class . ' s ON d.scheduleId = s.id
            JOIN ' . PatientProfileDoctrineEntity::class . ' p ON m.profileId = p.id
            LEFT JOIN ' . NotificationPreferencesDoctrineEntity::class . ' np ON p.accountId = np.accountId
            WHERE d.status = :pendingStatus
              AND d.reminderSentAt IS NULL
              AND d.scheduledAt >= :minWindow
              AND d.scheduledAt <= :maxWindow
            ORDER BY d.scheduledAt ASC
        ';

        $query = $this->entityManager->createQuery($dql);
        $query->setParameter('pendingStatus', 'pending');
        $query->setParameter('minWindow', $minWindow);
        $query->setParameter('maxWindow', $maxWindow);

        /** @var array<int, array{
         *     dose_id: string,
         *     scheduled_at: \DateTimeImmutable,
         *     medication_name: string,
         *     schedule_dose_amount: ?string,
         *     schedule_dose_unit: ?string,
         *     account_id: string,
         *     dose_reminders_enabled: ?bool,
         *     in_app_banners_enabled: ?bool,
         *     reminder_minutes_before: ?int
         * }> $rows */
        $rows = $query->getResult();

        $dueReminders = [];

        foreach ($rows as $row) {
            $minutesBefore = $row['reminder_minutes_before'] ?? 0;

            if (ReminderDispatchPolicy::isDue($row['scheduled_at'], $minutesBefore, $now)) {
                $dueReminders[] = new DueDoseReminder(
                    new DoseEventId($row['dose_id']),
                    new UserId($row['account_id']),
                    $row['medication_name'],
                    Dose::tryFromStorage($row['schedule_dose_amount'], $row['schedule_dose_unit']),
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
