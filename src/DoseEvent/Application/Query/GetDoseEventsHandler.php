<?php

declare(strict_types=1);

namespace DoseEvent\Application\Query;

use DoseEvent\Domain\DoseEvent;
use DoseEvent\Domain\DoseEventRepository;
use Medication\Domain\MedicationRepository;
use Profile\Domain\ProfileRepository;
use Schedule\Domain\ScheduleRepository;
use Shared\Domain\Result;
use Shared\Domain\Failure;
use Shared\Domain\ValueObject\ProfileId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GetDoseEventsHandler
{
    public function __construct(
        private readonly DoseEventRepository $doseEventRepository,
        private readonly ScheduleRepository $scheduleRepository,
        private readonly MedicationRepository $medicationRepository,
        private readonly ProfileRepository $profileRepository
    ) {
    }

    /**
     * @return Result<array<array<string, mixed>>>
     */
    public function __invoke(GetDoseEventsQuery $query): Result
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
        $scheduleIds = array_map(static fn ($sch) => $sch->id(), $schedules);

        $events = $this->doseEventRepository->findByScheduleIdsAndRange($scheduleIds, $query->from, $query->to);

        $scheduleById = [];
        foreach ($schedules as $schedule) {
            $scheduleById[$schedule->id()->value()] = $schedule;
        }

        $data = array_map(static function (DoseEvent $event) use ($scheduleById) {
            $schedule = $scheduleById[$event->scheduleId()->value()] ?? null;

            return [
                'id' => $event->id()->value(),
                'medicationId' => $event->medicationId()->value(),
                'scheduleId' => $event->scheduleId()->value(),
                'scheduledAt' => $event->scheduledAt()->format(\DateTimeInterface::ATOM),
                'status' => $event->status(),
                'takenAt' => $event->takenAt()?->format(\DateTimeInterface::ATOM),
                'dose' => $schedule?->dose()?->toApiArray(),
                'clientId' => $event->clientId(),
                'createdAt' => $event->createdAt()->format(\DateTimeInterface::ATOM),
                'updatedAt' => $event->updatedAt()->format(\DateTimeInterface::ATOM),
            ];
        }, $events);

        return Result::success($data);
    }
}
