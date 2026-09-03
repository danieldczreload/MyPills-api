<?php

declare(strict_types=1);

namespace DoseEvent\Application;

use DoseEvent\Domain\DoseEventExpander;
use DoseEvent\Domain\DoseEventRepository;
use Schedule\Domain\Schedule;

final class MaterializeUpcomingOccurrences
{
    public function __construct(
        private readonly DoseEventRepository $doseEventRepository,
        private readonly DoseEventExpander $doseEventExpander
    ) {
    }

    public function materialize(
        Schedule $schedule,
        \DateTimeZone $timezone,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to
    ): int {
        if ($schedule->isCancelled()) {
            return 0;
        }

        $occurrences = $this->doseEventExpander->expand($schedule, $from, $to, $timezone);
        $existing = $this->doseEventRepository->findByScheduleIdsAndRange([$schedule->id()], $from, $to);
        $existingTimes = array_map(
            static fn ($event) => $event->scheduledAt()->format(\DateTimeInterface::ATOM),
            $existing
        );

        $created = 0;
        foreach ($occurrences as $occurrence) {
            $formattedTime = $occurrence->scheduledAt()->format(\DateTimeInterface::ATOM);
            if (!in_array($formattedTime, $existingTimes, true)) {
                $this->doseEventRepository->save($occurrence);
                ++$created;
            }
        }

        return $created;
    }
}
