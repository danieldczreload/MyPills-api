<?php

declare(strict_types=1);

namespace DoseEvent\Domain;

use Schedule\Domain\DailyIntervalSchedule;
use Schedule\Domain\DailySchedule;
use Schedule\Domain\Schedule;
use Schedule\Domain\SpecificDaysSchedule;
use Schedule\Domain\ValueObject\TimeOfDay;
use Shared\Domain\ValueObject\DoseEventId;

final class DoseEventExpander
{
    /**
     * @return DoseEvent[]
     */
    public function expand(Schedule $schedule, \DateTimeImmutable $from, \DateTimeImmutable $to, \DateTimeZone $timezone): array
    {
        $occurrences = [];
        $utc = new \DateTimeZone('UTC');

        $startDate = new \DateTimeImmutable($schedule->startDate()->format('Y-m-d'), $timezone);
        $endDate = $schedule->endDate() !== null
            ? (new \DateTimeImmutable($schedule->endDate()->format('Y-m-d'), $timezone))->setTime(23, 59, 59)
            : null;

        $fromDay = $from->setTimezone($timezone)->format('Y-m-d');
        $toDay = $to->setTimezone($timezone)->format('Y-m-d');
        $startDay = $startDate->format('Y-m-d');
        $endDay = $endDate?->format('Y-m-d');

        $currentDay = $fromDay > $startDay ? $fromDay : $startDay;
        $lastDay = $endDay !== null && $endDay < $toDay ? $endDay : $toDay;

        while ($currentDay <= $lastDay) {
            $localDay = new \DateTimeImmutable($currentDay, $timezone);
            foreach ($this->occurrenceTimesForDay($schedule, $localDay, $timezone) as $occurrenceTime) {
                if ($occurrenceTime < $from || $occurrenceTime > $to) {
                    continue;
                }
                if ($occurrenceTime < $startDate || ($endDate !== null && $occurrenceTime > $endDate)) {
                    continue;
                }

                $occurrences[] = DoseEvent::create(
                    DoseEventId::generate(),
                    $schedule->medicationId(),
                    $schedule->id(),
                    $occurrenceTime->setTimezone($utc)
                );
            }

            $currentDay = (new \DateTimeImmutable($currentDay.' +1 day', $timezone))->format('Y-m-d');
        }

        return $occurrences;
    }

    /**
     * @return \DateTimeImmutable[]
     */
    private function occurrenceTimesForDay(Schedule $schedule, \DateTimeImmutable $localDay, \DateTimeZone $timezone): array
    {
        if ($schedule instanceof DailySchedule) {
            return array_map(
                static fn (TimeOfDay $time): \DateTimeImmutable => $localDay->setTime($time->hour(), $time->minute(), 0),
                $schedule->timesOfDay()
            );
        }

        if ($schedule instanceof DailyIntervalSchedule) {
            $startAt = $schedule->startAt();
            $endAt = $schedule->endAt();

            $intervalStart = $localDay->setTime($startAt->hour(), $startAt->minute(), 0);
            $intervalEnd = $endAt !== null
                ? $localDay->setTime($endAt->hour(), $endAt->minute(), 0)
                : $localDay->setTime(23, 59, 59);

            // If endAt is earlier than startAt, the interval spans midnight into the next local day.
            if ($endAt !== null && $intervalEnd < $intervalStart) {
                $nextDay = (new \DateTimeImmutable($localDay->format('Y-m-d').' +1 day', $timezone))->format('Y-m-d');
                $intervalEnd = (new \DateTimeImmutable($nextDay, $timezone))
                    ->setTime($endAt->hour(), $endAt->minute(), 0);
            }

            $times = [];
            $occTime = $intervalStart;
            while ($occTime <= $intervalEnd) {
                $times[] = $occTime;
                $occTime = $occTime->modify(sprintf('+%d hours', $schedule->everyHours()));
            }

            return $times;
        }

        if ($schedule instanceof SpecificDaysSchedule) {
            $dayOfWeek = (int) $localDay->format('N');
            if (!in_array($dayOfWeek, $schedule->daysOfWeek(), true)) {
                return [];
            }

            return array_map(
                static fn (TimeOfDay $time): \DateTimeImmutable => $localDay->setTime($time->hour(), $time->minute(), 0),
                $schedule->timesOfDay()
            );
        }

        return [];
    }
}
