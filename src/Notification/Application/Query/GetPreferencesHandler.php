<?php

declare(strict_types=1);

namespace Notification\Application\Query;

use Notification\Domain\NotificationPreferences;
use Notification\Domain\NotificationPreferencesRepository;
use Shared\Domain\Result;
use Shared\Domain\ValueObject\UserId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GetPreferencesHandler
{
    public function __construct(
        private readonly NotificationPreferencesRepository $preferencesRepository
    ) {
    }

    /**
     * @return Result<array<string, mixed>>
     */
    public function __invoke(GetPreferencesQuery $query): Result
    {
        $accountId = new UserId($query->accountId);
        $preferences = $this->preferencesRepository->findByAccountId($accountId);

        if ($preferences === null) {
            $preferences = NotificationPreferences::createDefault($accountId);
            $this->preferencesRepository->save($preferences);
        }

        return Result::success([
            'doseRemindersEnabled' => $preferences->doseRemindersEnabled(),
            'missedDoseNudgesEnabled' => $preferences->missedDoseNudgesEnabled(),
            'refillAlertsEnabled' => $preferences->refillAlertsEnabled(),
            'weeklyStreakSummariesEnabled' => $preferences->weeklyStreakSummariesEnabled(),
            'inAppBannersEnabled' => $preferences->inAppBannersEnabled(),
            'reminderMinutesBefore' => $preferences->reminderMinutesBefore(),
        ]);
    }
}
