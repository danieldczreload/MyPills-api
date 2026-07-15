<?php

declare(strict_types=1);

namespace Schedule\Application\Command;

use Medication\Domain\MedicationRepository;
use Profile\Domain\ProfileRepository;
use Schedule\Domain\ScheduleRepository;
use Schedule\Domain\ScheduleDeletedEvent;
use Shared\Application\Bus\EventBus;
use Shared\Domain\Result;
use Shared\Domain\Failure;
use Shared\Domain\ValueObject\ProfileId;
use Shared\Domain\ValueObject\ScheduleId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class DeleteScheduleHandler
{
    public function __construct(
        private readonly ScheduleRepository $scheduleRepository,
        private readonly MedicationRepository $medicationRepository,
        private readonly ProfileRepository $profileRepository,
        private readonly EventBus $eventBus
    ) {
    }

    /**
     * @return Result<null>
     */
    public function __invoke(DeleteScheduleCommand $command): Result
    {
        $profileId = new ProfileId($command->profileId);
        $profile = $this->profileRepository->findById($profileId);

        if ($profile === null) {
            return Result::failure(Failure::notFound('Profile not found.'));
        }

        if ($profile->accountId()->value() !== $command->accountId) {
            return Result::failure(Failure::forbidden('You do not own this profile.'));
        }

        $scheduleId = new ScheduleId($command->id);
        $schedule = $this->scheduleRepository->findById($scheduleId);

        if ($schedule === null) {
            return Result::failure(Failure::notFound('Schedule not found.'));
        }

        $medication = $this->medicationRepository->findById($schedule->medicationId());
        if ($medication === null || !$medication->profileId()->equals($profileId)) {
            return Result::failure(Failure::forbidden('Schedule does not belong to this profile.'));
        }

        $this->scheduleRepository->delete($schedule);

        // Dispatch domain event to clean up DoseEvents and/or Calendar events
        $this->eventBus->publish(new ScheduleDeletedEvent($schedule->id()->value(), $profile->id()->value()));

        return Result::success();
    }
}
