<?php

declare(strict_types=1);

namespace DoseEvent\Application\Command;

use DoseEvent\Application\MaterializeUpcomingOccurrences;
use DoseEvent\Domain\DoseEventsExpandedEvent;
use Medication\Domain\MedicationRepository;
use Profile\Domain\ProfileRepository;
use Profile\Domain\ValueObject\Timezone;
use Schedule\Domain\ScheduleRepository;
use Shared\Application\Bus\EventBus;
use Shared\Domain\Result;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ExpandDoseEventsHandler
{
    public function __construct(
        private readonly ScheduleRepository $scheduleRepository,
        private readonly MaterializeUpcomingOccurrences $materializeUpcomingOccurrences,
        private readonly MedicationRepository $medicationRepository,
        private readonly ProfileRepository $profileRepository,
        private readonly EventBus $eventBus
    ) {
    }

    /**
     * @return Result<array{schedulesScanned: int, doseEventsCreated: int, profilesQueuedForCalendarSync: int}>
     */
    public function __invoke(ExpandDoseEventsCommand $command): Result
    {
        $now = $command->referenceTime ?? new \DateTimeImmutable();
        $to = $now->modify('+14 days');
        $schedules = $this->scheduleRepository->findAll();
        /** @var array<string, string> $affectedProfiles */
        $affectedProfiles = [];
        $created = 0;
        $scanned = 0;

        foreach ($schedules as $schedule) {
            if ($schedule->isCancelled()) {
                continue;
            }

            ++$scanned;
            $profileId = null;
            $timezone = new \DateTimeZone('UTC');
            $medication = $this->medicationRepository->findById($schedule->medicationId());
            if ($medication !== null) {
                $profile = $this->profileRepository->findById($medication->profileId());
                if ($profile !== null) {
                    $profileId = $profile->id()->value();
                    $timezone = Timezone::dateTimeZoneOrUtc($profile->timezone());
                }
            }

            $newCount = $this->materializeUpcomingOccurrences->materialize($schedule, $timezone, $now, $to);
            $created += $newCount;

            if ($newCount > 0 && $profileId !== null) {
                $affectedProfiles[$profileId] = $schedule->id()->value();
            }
        }

        foreach ($affectedProfiles as $profileId => $scheduleId) {
            $this->eventBus->publish(new DoseEventsExpandedEvent($profileId, $scheduleId));
        }

        return Result::success([
            'schedulesScanned' => $scanned,
            'doseEventsCreated' => $created,
            'profilesQueuedForCalendarSync' => count($affectedProfiles),
        ]);
    }
}
