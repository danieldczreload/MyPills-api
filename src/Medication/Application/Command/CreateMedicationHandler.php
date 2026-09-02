<?php

declare(strict_types=1);

namespace Medication\Application\Command;

use Medication\Application\MedicationView;
use Medication\Domain\Medication;
use Medication\Domain\MedicationRepository;
use Profile\Domain\ProfileRepository;
use Shared\Domain\Result;
use Shared\Domain\Failure;
use Shared\Domain\ValueObject\MedicationId;
use Shared\Domain\ValueObject\ProfileId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CreateMedicationHandler
{
    public function __construct(
        private readonly MedicationRepository $medicationRepository,
        private readonly ProfileRepository $profileRepository
    ) {
    }

    /**
     * @return Result<array{id: string, profileId: string, name: string, instructions: ?string, photoUrl: ?string, clientId: ?string, form: string, colorToken: string, createdAt: string, updatedAt: string}>
     */
    public function __invoke(CreateMedicationCommand $command): Result
    {
        $profileId = new ProfileId($command->profileId);
        $profile = $this->profileRepository->findById($profileId);

        if ($profile === null) {
            return Result::failure(Failure::notFound('Profile not found.'));
        }

        if ($profile->accountId()->value() !== $command->accountId) {
            return Result::failure(Failure::forbidden('You do not own this profile.'));
        }

        if (trim($command->name) === '') {
            return Result::failure(Failure::validation('Medication name cannot be empty.'));
        }

        // Check idempotency if clientId is provided
        if ($command->clientId !== null && $command->clientId !== '') {
            $existing = $this->medicationRepository->findByClientId($command->clientId);
            if ($existing !== null) {
                return Result::success(MedicationView::toArray($existing));
            }
        }

        $medication = Medication::create(
            MedicationId::generate(),
            $profileId,
            $command->name,
            $command->instructions,
            $command->photoUrl,
            $command->clientId,
            $command->form,
            $command->colorToken
        );

        $this->medicationRepository->save($medication);

        return Result::success(MedicationView::toArray($medication));
    }
}
