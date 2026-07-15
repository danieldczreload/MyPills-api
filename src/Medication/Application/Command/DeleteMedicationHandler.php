<?php

declare(strict_types=1);

namespace Medication\Application\Command;

use Medication\Domain\MedicationRepository;
use Medication\Domain\MedicationDeletedEvent;
use Profile\Domain\ProfileRepository;
use Shared\Application\Bus\EventBus;
use Shared\Domain\Result;
use Shared\Domain\Failure;
use Shared\Domain\ValueObject\MedicationId;
use Shared\Domain\ValueObject\ProfileId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class DeleteMedicationHandler
{
    public function __construct(
        private readonly MedicationRepository $medicationRepository,
        private readonly ProfileRepository $profileRepository,
        private readonly EventBus $eventBus
    ) {
    }

    /**
     * @return Result<null>
     */
    public function __invoke(DeleteMedicationCommand $command): Result
    {
        $profileId = new ProfileId($command->profileId);
        $profile = $this->profileRepository->findById($profileId);

        if ($profile === null) {
            return Result::failure(Failure::notFound('Profile not found.'));
        }

        if ($profile->accountId()->value() !== $command->accountId) {
            return Result::failure(Failure::forbidden('You do not own this profile.'));
        }

        $medicationId = new MedicationId($command->id);
        $medication = $this->medicationRepository->findById($medicationId);

        if ($medication === null) {
            return Result::failure(Failure::notFound('Medication not found.'));
        }

        if (!$medication->profileId()->equals($profileId)) {
            return Result::failure(Failure::forbidden('Medication does not belong to this profile.'));
        }

        $this->medicationRepository->delete($medication);

        $this->eventBus->publish(new MedicationDeletedEvent($medication->id()->value(), $profile->id()->value()));

        return Result::success();
    }
}
