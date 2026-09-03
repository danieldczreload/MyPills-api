<?php

declare(strict_types=1);

namespace CalendarIntegration\Application\Event;

use CalendarIntegration\Application\CalendarEventRemover;
use CalendarIntegration\Domain\CalendarEventMappingRepository;
use DoseEvent\Domain\DoseEvent;
use DoseEvent\Domain\DoseEventRepository;
use Medication\Domain\Medication;
use Medication\Domain\MedicationRepository;
use Profile\Domain\ProfileTimezoneChangedEvent;
use Schedule\Domain\Schedule;
use Schedule\Domain\ScheduleRepository;
use Shared\Domain\ValueObject\MedicationId;
use Shared\Domain\ValueObject\ProfileId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(priority: 10)]
final class ProfileTimezoneChangedHandler
{
    public function __construct(
        private readonly MedicationRepository $medicationRepository,
        private readonly ScheduleRepository $scheduleRepository,
        private readonly DoseEventRepository $doseEventRepository,
        private readonly CalendarEventMappingRepository $mappingRepository,
        private readonly CalendarEventRemover $calendarEventRemover
    ) {
    }

    public function __invoke(ProfileTimezoneChangedEvent $event): void
    {
        $profileId = new ProfileId($event->profileId);
        $medications = $this->medicationRepository->findByProfileId($profileId);
        $medicationIds = array_map(static fn (Medication $medication): MedicationId => $medication->id(), $medications);
        $schedules = $medicationIds === [] ? [] : $this->scheduleRepository->findByMedicationIds($medicationIds);
        $scheduleIds = array_map(static fn (Schedule $schedule) => $schedule->id(), $schedules);

        $pendingDoseEvents = $scheduleIds === [] ? [] : $this->doseEventRepository->findPendingByScheduleIds($scheduleIds);
        $pendingDoseEventIds = array_map(static fn (DoseEvent $dose): string => $dose->id()->value(), $pendingDoseEvents);
        if ($pendingDoseEventIds === []) {
            return;
        }

        $mappings = $this->mappingRepository->findByDoseEventIds($pendingDoseEventIds);
        if ($mappings !== []) {
            $this->calendarEventRemover->remove($profileId, $mappings);
        }

        $keptDoseEventIds = [];
        foreach ($this->mappingRepository->findByDoseEventIds($pendingDoseEventIds) as $mapping) {
            $keptDoseEventIds[$mapping->doseEventId()] = true;
        }

        foreach ($pendingDoseEvents as $doseEvent) {
            if (!isset($keptDoseEventIds[$doseEvent->id()->value()])) {
                continue;
            }

            $doseEvent->markAs('skipped');
            $this->doseEventRepository->save($doseEvent);
        }
    }
}
