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

        $startDate = $schedule->startDate()->setTimezone($timezone)->setTime(0, 0, 0);
        $endDate = $schedule->endDate()?->setTimezone($timezone)->setTime(23, 59, 59);

        $fromDay = $from->setTimezone($timezone)->setTime(0, 0, 0);
        $toDay = $to->setTimezone($timezone)->setTime(23, 59, 59);

        $current = $fromDay < $startDate ? $startDate : $fromDay;
        $lastDay = $endDate !== null && $endDate < $toDay ? $endDate : $toDay;

        while ($current <= $lastDay) {
            foreach ($this->occurrenceTimesForDay($schedule, $current) as $occurrenceTime) {
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
                    $occurrenceTime
                );
            }

            $current = $current->modify('+1 day');
        }

        return $occurrences;
    }

    /**
     * @return \DateTimeImmutable[]
     */
    private function occurrenceTimesForDay(Schedule $schedule, \DateTimeImmutable $localDay): array
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
            if ($intervalEnd < $intervalStart) {
                $intervalEnd = $intervalEnd->modify('+1 day');
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
