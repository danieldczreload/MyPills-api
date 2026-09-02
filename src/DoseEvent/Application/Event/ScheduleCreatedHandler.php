<?php

declare(strict_types=1);

namespace DoseEvent\Application\Event;

use DoseEvent\Domain\DoseEventExpander;
use DoseEvent\Domain\DoseEventRepository;
use DoseEvent\Domain\DoseEventsExpandedEvent;
use Profile\Domain\ProfileRepository;
use Profile\Domain\ValueObject\Timezone;
use Schedule\Domain\ScheduleCreatedEvent;
use Schedule\Domain\ScheduleRepository;
use Shared\Application\Bus\EventBus;
use Shared\Domain\ValueObject\ProfileId;
use Shared\Domain\ValueObject\ScheduleId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ScheduleCreatedHandler
{
    public function __construct(
        private readonly ScheduleRepository $scheduleRepository,
        private readonly DoseEventRepository $doseEventRepository,
        private readonly DoseEventExpander $doseEventExpander,
        private readonly EventBus $eventBus,
        private readonly ProfileRepository $profileRepository
    ) {
    }

    public function __invoke(ScheduleCreatedEvent $event): void
    {
        $schedule = $this->scheduleRepository->findById(new ScheduleId($event->scheduleId));
        if ($schedule === null) {
            return;
        }

        $profile = $this->profileRepository->findById(new ProfileId($event->profileId));
        $timezone = Timezone::dateTimeZoneOrUtc($profile?->timezone() ?? 'UTC');

        $now = new \DateTimeImmutable();
        $to = $now->modify('+14 days');

        $occurrences = $this->doseEventExpander->expand($schedule, $now, $to, $timezone);

        // Fetch existing dose events in this range to avoid duplicates
        $existing = $this->doseEventRepository->findByScheduleIdsAndRange([$schedule->id()], $now, $to);
        $existingTimes = array_map(static fn ($e) => $e->scheduledAt()->format(\DateTimeInterface::ATOM), $existing);

        $created = 0;
        foreach ($occurrences as $occurrence) {
            $formattedTime = $occurrence->scheduledAt()->format(\DateTimeInterface::ATOM);
            if (!in_array($formattedTime, $existingTimes, true)) {
                $this->doseEventRepository->save($occurrence);
                ++$created;
            }
        }

        if ($created > 0) {
            $this->eventBus->publish(new DoseEventsExpandedEvent($event->profileId, $event->scheduleId));
        }
    }
}
