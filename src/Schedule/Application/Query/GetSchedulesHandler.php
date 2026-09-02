<?php

declare(strict_types=1);

namespace Schedule\Application\Query;

use Medication\Domain\MedicationRepository;
use Profile\Domain\ProfileRepository;
use Schedule\Application\ScheduleView;
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

        $data = array_map(ScheduleView::toArray(...), $schedules);

        return Result::success($data);
    }
}
