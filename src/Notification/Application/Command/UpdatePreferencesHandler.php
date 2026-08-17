<?php

declare(strict_types=1);

namespace Notification\Application\Command;

use Notification\Domain\NotificationPreferences;
use Notification\Domain\NotificationPreferencesRepository;
use Shared\Domain\Result;
use Shared\Domain\ValueObject\UserId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class UpdatePreferencesHandler
{
    public function __construct(
        private readonly NotificationPreferencesRepository $preferencesRepository
    ) {
    }

    /**
     * @return Result<array<string, mixed>>
     */
    public function __invoke(UpdatePreferencesCommand $command): Result
    {
        $accountId = new UserId($command->accountId);
        $preferences = $this->preferencesRepository->findByAccountId($accountId);

        if ($preferences === null) {
            $preferences = NotificationPreferences::createDefault($accountId);
        }

        $preferences->update(
            $command->doseRemindersEnabled,
            $command->missedDoseNudgesEnabled,
            $command->refillAlertsEnabled,
            $command->weeklyStreakSummariesEnabled,
            $command->inAppBannersEnabled,
            $command->reminderMinutesBefore
        );

        $this->preferencesRepository->save($preferences);

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
