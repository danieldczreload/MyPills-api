<?php

declare(strict_types=1);

namespace DoseEvent\Application\Event;

use DoseEvent\Application\MaterializeUpcomingOccurrences;
use DoseEvent\Domain\DoseEventRepository;
use DoseEvent\Domain\DoseEventsExpandedEvent;
use Medication\Domain\Medication;
use Medication\Domain\MedicationRepository;
use Profile\Domain\ProfileTimezoneChangedEvent;
use Profile\Domain\ValueObject\Timezone;
use Schedule\Domain\Schedule;
use Schedule\Domain\ScheduleRepository;
use Shared\Application\Bus\EventBus;
use Shared\Domain\ValueObject\MedicationId;
use Shared\Domain\ValueObject\ProfileId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(priority: 0)]
final class ProfileTimezoneChangedHandler
{
    public function __construct(
        private readonly MedicationRepository $medicationRepository,
        private readonly ScheduleRepository $scheduleRepository,
        private readonly DoseEventRepository $doseEventRepository,
        private readonly MaterializeUpcomingOccurrences $materializeUpcomingOccurrences,
        private readonly EventBus $eventBus
    ) {
    }

    public function __invoke(ProfileTimezoneChangedEvent $event): void
    {
        $profileId = new ProfileId($event->profileId);
        $medications = $this->medicationRepository->findByProfileId($profileId);
        $medicationIds = array_map(static fn (Medication $medication): MedicationId => $medication->id(), $medications);
        $schedules = $medicationIds === [] ? [] : $this->scheduleRepository->findByMedicationIds($medicationIds);
        /** @var Schedule[] $activeSchedules */
        $activeSchedules = array_values(array_filter(
            $schedules,
            static fn (Schedule $schedule): bool => !$schedule->isCancelled()
        ));
        $scheduleIds = array_map(static fn (Schedule $schedule) => $schedule->id(), $activeSchedules);

        if ($scheduleIds !== []) {
            $this->doseEventRepository->deletePendingByScheduleIds($scheduleIds);
        }

        $timezone = Timezone::dateTimeZoneOrUtc($event->timezone);
        $now = new \DateTimeImmutable();
        $to = $now->modify('+14 days');
        $created = 0;
        foreach ($activeSchedules as $schedule) {
            $created += $this->materializeUpcomingOccurrences->materialize($schedule, $timezone, $now, $to);
        }

        if ($created > 0) {
            $this->eventBus->publish(new DoseEventsExpandedEvent(
                $event->profileId,
                $activeSchedules[0]->id()->value()
            ));
        }
    }
}
