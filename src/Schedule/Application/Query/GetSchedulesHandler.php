<?php

declare(strict_types=1);

namespace Schedule\Application\Query;

use Medication\Domain\MedicationRepository;
use Profile\Domain\ProfileRepository;
use Schedule\Domain\Schedule;
use Schedule\Domain\DailySchedule;
use Schedule\Domain\DailyIntervalSchedule;
use Schedule\Domain\SpecificDaysSchedule;
use Schedule\Domain\ValueObject\TimeOfDay;
use Schedule\Domain\ScheduleRepository;
use Shared\Domain\Result;
use Shared\Domain\Failure;
use Shared\Domain\ValueObject\ProfileId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GetSchedulesHandler
{
    public function __construct(
        private readonly ScheduleRepository $scheduleRepository,
        private readonly MedicationRepository $medicationRepository,
        private readonly ProfileRepository $profileRepository
    ) {
    }

    /**
     * @return Result<array<array<string, mixed>>>
     */
    public function __invoke(GetSchedulesQuery $query): Result
    {
        $profileId = new ProfileId($query->profileId);
        $profile = $this->profileRepository->findById($profileId);

        if ($profile === null) {
            return Result::failure(Failure::notFound('Profile not found.'));
        }

        if ($profile->accountId()->value() !== $query->accountId) {
            return Result::failure(Failure::forbidden('You do not own this profile.'));
        }

        $medications = $this->medicationRepository->findByProfileId($profileId);
        $medicationIds = array_map(static fn ($med) => $med->id(), $medications);

        $schedules = $this->scheduleRepository->findByMedicationIds($medicationIds);

        $data = array_map(function (Schedule $schedule) {
            $base = [
                'id' => $schedule->id()->value(),
                'medicationId' => $schedule->medicationId()->value(),
                'type' => $schedule->type(),
                'startDate' => $schedule->startDate()->format(\DateTimeInterface::ATOM),
                'endDate' => $schedule->endDate()?->format(\DateTimeInterface::ATOM),
                'clientId' => $schedule->clientId(),
                'createdAt' => $schedule->createdAt()->format(\DateTimeInterface::ATOM),
                'updatedAt' => $schedule->updatedAt()->format(\DateTimeInterface::ATOM),
            ];

            if ($schedule instanceof DailySchedule) {
                $base['timesOfDay'] = array_map(static fn (TimeOfDay $t) => ['hour' => $t->hour(), 'minute' => $t->minute()], $schedule->timesOfDay());
            } elseif ($schedule instanceof DailyIntervalSchedule) {
                $base['everyHours'] = $schedule->everyHours();
                $base['startAt'] = ['hour' => $schedule->startAt()->hour(), 'minute' => $schedule->startAt()->minute()];
                $base['endAt'] = $schedule->endAt() ? ['hour' => $schedule->endAt()->hour(), 'minute' => $schedule->endAt()->minute()] : null;
            } elseif ($schedule instanceof SpecificDaysSchedule) {
                $base['daysOfWeek'] = $schedule->daysOfWeek();
                $base['timesOfDay'] = array_map(static fn (TimeOfDay $t) => ['hour' => $t->hour(), 'minute' => $t->minute()], $schedule->timesOfDay());
            }

            return $base;
        }, $schedules);

        return Result::success($data);
    }
}
