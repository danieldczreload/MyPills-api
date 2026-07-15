<?php

declare(strict_types=1);

namespace DoseEvent\Domain;

use Schedule\Domain\Schedule;
use Schedule\Domain\DailySchedule;
use Schedule\Domain\DailyIntervalSchedule;
use Schedule\Domain\SpecificDaysSchedule;
use Shared\Domain\ValueObject\DoseEventId;

final class DoseEventExpander
{
    /**
     * @return DoseEvent[]
     */
    public function expand(Schedule $schedule, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $occurrences = [];
        $startDate = $schedule->startDate();
        $endDate = $schedule->endDate();

        // Standardize $from and $to to midnight for day-based looping
        $fromDay = $from->setTime(0, 0, 0);
        $toDay = $to->setTime(23, 59, 59);

        if ($schedule instanceof DailySchedule) {
            $current = $fromDay;
            while ($current <= $toDay) {
                if ($current >= $startDate->setTime(0, 0, 0) && ($endDate === null || $current <= $endDate->setTime(23, 59, 59))) {
                    foreach ($schedule->timesOfDay() as $time) {
                        $occurrenceTime = $current->setTime($time->hour(), $time->minute(), 0);
                        if ($occurrenceTime >= $from && $occurrenceTime <= $to && $occurrenceTime >= $startDate && ($endDate === null || $occurrenceTime <= $endDate)) {
                            $occurrences[] = DoseEvent::create(
                                DoseEventId::generate(),
                                $schedule->medicationId(),
                                $schedule->id(),
                                $occurrenceTime
                            );
                        }
                    }
                }
                $current = $current->modify('+1 day');
            }
        } elseif ($schedule instanceof DailyIntervalSchedule) {
            $current = $fromDay;
            while ($current <= $toDay) {
                if ($current >= $startDate->setTime(0, 0, 0) && ($endDate === null || $current <= $endDate->setTime(23, 59, 59))) {
                    $startAt = $schedule->startAt();
                    $endAt = $schedule->endAt();

                    $intervalStart = $current->setTime($startAt->hour(), $startAt->minute(), 0);
                    $intervalEnd = $endAt !== null
                        ? $current->setTime($endAt->hour(), $endAt->minute(), 0)
                        : $current->setTime(23, 59, 59);

                    // If endAt is earlier than startAt, it might span to the next day
                    if ($intervalEnd < $intervalStart) {
                        $intervalEnd = $intervalEnd->modify('+1 day');
                    }

                    $occTime = $intervalStart;
                    while ($occTime <= $intervalEnd) {
                        if ($occTime >= $from && $occTime <= $to && $occTime >= $startDate && ($endDate === null || $occTime <= $endDate)) {
                            $occurrences[] = DoseEvent::create(
                                DoseEventId::generate(),
                                $schedule->medicationId(),
                                $schedule->id(),
                                $occTime
                            );
                        }
                        $occTime = $occTime->modify(sprintf('+%d hours', $schedule->everyHours()));
                    }
                }
                $current = $current->modify('+1 day');
            }
        } elseif ($schedule instanceof SpecificDaysSchedule) {
            $current = $fromDay;
            while ($current <= $toDay) {
                if ($current >= $startDate->setTime(0, 0, 0) && ($endDate === null || $current <= $endDate->setTime(23, 59, 59))) {
                    $dayOfWeek = (int) $current->format('N'); // 1 (Monday) to 7 (Sunday)
                    if (in_array($dayOfWeek, $schedule->daysOfWeek(), true)) {
                        foreach ($schedule->timesOfDay() as $time) {
                            $occurrenceTime = $current->setTime($time->hour(), $time->minute(), 0);
                            if ($occurrenceTime >= $from && $occurrenceTime <= $to && $occurrenceTime >= $startDate && ($endDate === null || $occurrenceTime <= $endDate)) {
                                $occurrences[] = DoseEvent::create(
                                    DoseEventId::generate(),
                                    $schedule->medicationId(),
                                    $schedule->id(),
                                    $occurrenceTime
                                );
                            }
                        }
                    }
                }
                $current = $current->modify('+1 day');
            }
        }

        return $occurrences;
    }
}
