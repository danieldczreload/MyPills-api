<?php

declare(strict_types=1);

namespace Medication\Application\Command;

use Medication\Domain\MedicationRepository;
use Profile\Domain\ProfileRepository;
use Shared\Domain\Result;
use Shared\Domain\Failure;
use Shared\Domain\ValueObject\MedicationId;
use Shared\Domain\ValueObject\ProfileId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class UpdateMedicationHandler
{
    public function __construct(
        private readonly MedicationRepository $medicationRepository,
        private readonly ProfileRepository $profileRepository
    ) {
    }

    /**
     * @return Result<array{id: string, profileId: string, name: string, dosage: string, instructions: ?string, photoUrl: ?string, clientId: ?string, createdAt: string, updatedAt: string}>
     */
    public function __invoke(UpdateMedicationCommand $command): Result
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

        if (trim($command->name) === '') {
            return Result::failure(Failure::validation('Medication name cannot be empty.'));
        }

        if (trim($command->dosage) === '') {
            return Result::failure(Failure::validation('Dosage cannot be empty.'));
        }

        $medication->update($command->name, $command->dosage, $command->instructions, $command->photoUrl);
        $this->medicationRepository->save($medication);

        return Result::success([
            'id' => $medication->id()->value(),
            'profileId' => $medication->profileId()->value(),
            'name' => $medication->name(),
            'dosage' => $medication->dosage(),
            'instructions' => $medication->instructions(),
            'photoUrl' => $medication->photoUrl(),
            'clientId' => $medication->clientId(),
            'createdAt' => $medication->createdAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $medication->updatedAt()->format(\DateTimeInterface::ATOM),
        ]);
    }
}
