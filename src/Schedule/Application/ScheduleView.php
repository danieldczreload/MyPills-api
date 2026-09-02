<?php

declare(strict_types=1);

namespace Schedule\Application;

use Schedule\Domain\DailyIntervalSchedule;
use Schedule\Domain\DailySchedule;
use Schedule\Domain\Schedule;
use Schedule\Domain\SpecificDaysSchedule;
use Schedule\Domain\ValueObject\TimeOfDay;

final class ScheduleView
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(Schedule $schedule): array
    {
        $base = [
            'id' => $schedule->id()->value(),
            'medicationId' => $schedule->medicationId()->value(),
            'type' => $schedule->type(),
            'startDate' => $schedule->startDate()->format(\DateTimeInterface::ATOM),
            'endDate' => $schedule->endDate()?->format(\DateTimeInterface::ATOM),
            'dose' => $schedule->dose()?->toApiArray(),
            'clientId' => $schedule->clientId(),
            'createdAt' => $schedule->createdAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $schedule->updatedAt()->format(\DateTimeInterface::ATOM),
        ];

        if ($schedule instanceof DailySchedule) {
            $base['timesOfDay'] = array_map(
                static fn (TimeOfDay $t) => ['hour' => $t->hour(), 'minute' => $t->minute()],
                $schedule->timesOfDay()
            );
        } elseif ($schedule instanceof DailyIntervalSchedule) {
            $base['everyHours'] = $schedule->everyHours();
            $base['startAt'] = ['hour' => $schedule->startAt()->hour(), 'minute' => $schedule->startAt()->minute()];
            $base['endAt'] = $schedule->endAt() !== null
                ? ['hour' => $schedule->endAt()->hour(), 'minute' => $schedule->endAt()->minute()]
                : null;
        } elseif ($schedule instanceof SpecificDaysSchedule) {
            $base['daysOfWeek'] = $schedule->daysOfWeek();
            $base['timesOfDay'] = array_map(
                static fn (TimeOfDay $t) => ['hour' => $t->hour(), 'minute' => $t->minute()],
                $schedule->timesOfDay()
            );
        }

        return $base;
    }
}
