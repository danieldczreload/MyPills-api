<?php

declare(strict_types=1);

namespace Profile\Application\Query;

use DoseEvent\Domain\DoseEventRepository;
use Medication\Domain\MedicationRepository;
use Profile\Domain\ProfileRepository;
use Profile\Domain\TombstoneRepository;
use Schedule\Domain\ScheduleRepository;
use Schedule\Domain\Schedule;
use Schedule\Domain\DailySchedule;
use Schedule\Domain\DailyIntervalSchedule;
use Schedule\Domain\SpecificDaysSchedule;
use Schedule\Domain\ValueObject\TimeOfDay;
use Shared\Domain\Result;
use Shared\Domain\Failure;
use Shared\Domain\ValueObject\ProfileId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SyncProfileHandler
{
    public function __construct(
        private readonly ProfileRepository $profileRepository,
        private readonly MedicationRepository $medicationRepository,
        private readonly ScheduleRepository $scheduleRepository,
        private readonly DoseEventRepository $doseEventRepository,
        private readonly TombstoneRepository $tombstoneRepository
    ) {
    }

    /**
     * @return Result<array{
     *     medications: array<array<string, mixed>>,
     *     schedules: array<array<string, mixed>>,
     *     doseEvents: array<array<string, mixed>>,
     *     tombstones: array<array{id: string, type: string, deletedAt: string}>
     * }>
     */
    public function __invoke(SyncProfileQuery $query): Result
    {
        $profileId = new ProfileId($query->profileId);
        $profile = $this->profileRepository->findById($profileId);

        if ($profile === null) {
            return Result::failure(Failure::notFound('Profile not found.'));
        }

        if ($profile->accountId()->value() !== $query->accountId) {
            return Result::failure(Failure::forbidden('You do not own this profile.'));
        }

        // 1. Fetch Medications
        $medications = $this->medicationRepository->findByProfileId($profileId);
        $filteredMedications = array_filter(
            $medications,
            fn ($med) => $med->updatedAt() >= $query->since
        );
        $formattedMedications = array_map(static function ($medication) {
            return [
                'id' => $medication->id()->value(),
                'profileId' => $medication->profileId()->value(),
                'name' => $medication->name(),
                'dosage' => $medication->dosage(),
                'instructions' => $medication->instructions(),
                'photoUrl' => $medication->photoUrl(),
                'clientId' => $medication->clientId(),
                'createdAt' => $medication->createdAt()->format(\DateTimeInterface::ATOM),
                'updatedAt' => $medication->updatedAt()->format(\DateTimeInterface::ATOM),
            ];
        }, $filteredMedications);

        // 2. Fetch Schedules
        $medicationIds = array_map(static fn ($med) => $med->id(), $medications);
        $schedules = $this->scheduleRepository->findByMedicationIds($medicationIds);
        $filteredSchedules = array_filter(
            $schedules,
            fn ($sch) => $sch->updatedAt() >= $query->since
        );
        $formattedSchedules = array_map(static function (Schedule $schedule) {
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
        }, $filteredSchedules);

        // 3. Fetch DoseEvents
        $scheduleIds = array_map(static fn ($sch) => $sch->id(), $schedules);
        // Fetch last 30 days up to next 30 days for sync buffer
        $from = $query->since->modify('-30 days');
        $to = $query->since->modify('+30 days');
        $doseEvents = $this->doseEventRepository->findByScheduleIdsAndRange($scheduleIds, $from, $to);
        $filteredDoseEvents = array_filter(
            $doseEvents,
            fn ($event) => $event->updatedAt() >= $query->since
        );
        $formattedDoseEvents = array_map(static function ($event) {
            return [
                'id' => $event->id()->value(),
                'medicationId' => $event->medicationId()->value(),
                'scheduleId' => $event->scheduleId()->value(),
                'scheduledAt' => $event->scheduledAt()->format(\DateTimeInterface::ATOM),
                'status' => $event->status(),
                'takenAt' => $event->takenAt()?->format(\DateTimeInterface::ATOM),
                'clientId' => $event->clientId(),
                'createdAt' => $event->createdAt()->format(\DateTimeInterface::ATOM),
                'updatedAt' => $event->updatedAt()->format(\DateTimeInterface::ATOM),
            ];
        }, $filteredDoseEvents);

        // 4. Fetch Tombstones
        $tombstones = $this->tombstoneRepository->findByProfileIdSince($profileId, $query->since);
        $formattedTombstones = array_map(static function ($t) {
            return [
                'id' => $t->entityId(),
                'type' => $t->entityType(),
                'deletedAt' => $t->deletedAt()->format(\DateTimeInterface::ATOM),
            ];
        }, $tombstones);

        return Result::success([
            'medications' => array_values($formattedMedications),
            'schedules' => array_values($formattedSchedules),
            'doseEvents' => array_values($formattedDoseEvents),
            'tombstones' => array_values($formattedTombstones),
        ]);
    }
}
