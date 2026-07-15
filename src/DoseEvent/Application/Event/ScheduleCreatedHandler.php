<?php

declare(strict_types=1);

namespace DoseEvent\Application\Event;

use DoseEvent\Domain\DoseEventRepository;
use DoseEvent\Domain\DoseEventExpander;
use Schedule\Domain\ScheduleRepository;
use Schedule\Domain\ScheduleCreatedEvent;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ScheduleCreatedHandler
{
    public function __construct(
        private readonly ScheduleRepository $scheduleRepository,
        private readonly DoseEventRepository $doseEventRepository,
        private readonly DoseEventExpander $doseEventExpander
    ) {
    }

    public function __invoke(ScheduleCreatedEvent $event): void
    {
        $schedule = $this->scheduleRepository->findById(new \Shared\Domain\ValueObject\ScheduleId($event->scheduleId));
        if ($schedule === null) {
            return;
        }

        $now = new \DateTimeImmutable();
        $to = $now->modify('+14 days');

        $occurrences = $this->doseEventExpander->expand($schedule, $now, $to);

        // Fetch existing dose events in this range to avoid duplicates
        $existing = $this->doseEventRepository->findByScheduleIdsAndRange([$schedule->id()], $now, $to);
        $existingTimes = array_map(static fn ($e) => $e->scheduledAt()->format(\DateTimeInterface::ATOM), $existing);

        foreach ($occurrences as $occurrence) {
            $formattedTime = $occurrence->scheduledAt()->format(\DateTimeInterface::ATOM);
            if (!in_array($formattedTime, $existingTimes, true)) {
                $this->doseEventRepository->save($occurrence);
            }
        }
    }
}
