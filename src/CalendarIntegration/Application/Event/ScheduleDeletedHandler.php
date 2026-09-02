<?php

declare(strict_types=1);

namespace CalendarIntegration\Application\Event;

use CalendarIntegration\Application\CalendarEventRemover;
use CalendarIntegration\Domain\CalendarEventMappingRepository;
use DoseEvent\Domain\DoseEvent;
use DoseEvent\Domain\DoseEventRepository;
use Schedule\Domain\ScheduleDeletedEvent;
use Shared\Domain\ValueObject\ProfileId;
use Shared\Domain\ValueObject\ScheduleId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ScheduleDeletedHandler
{
    public function __construct(
        private readonly CalendarEventMappingRepository $mappingRepository,
        private readonly DoseEventRepository $doseEventRepository,
        private readonly CalendarEventRemover $calendarEventRemover
    ) {
    }

    public function __invoke(ScheduleDeletedEvent $event): void
    {
        $profileId = new ProfileId($event->profileId);
        $scheduleId = new ScheduleId($event->scheduleId);

        $doseEvents = $this->doseEventRepository->findByScheduleId($scheduleId);
        $doseEventIds = array_map(static fn (DoseEvent $d): string => $d->id()->value(), $doseEvents);

        if ($doseEventIds === []) {
            return;
        }

        $mappings = $this->mappingRepository->findByDoseEventIds($doseEventIds);
        if ($mappings === []) {
            return;
        }

        $this->calendarEventRemover->remove($profileId, $mappings);
    }
}
