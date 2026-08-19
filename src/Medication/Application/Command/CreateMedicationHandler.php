<?php

declare(strict_types=1);

namespace Medication\Application\Command;

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
     * @return Result<array{id: string, profileId: string, name: string, dosage: string, instructions: ?string, photoUrl: ?string, clientId: ?string, form: string, colorToken: string, createdAt: string, updatedAt: string}>
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

        if (trim($command->dosage) === '') {
            return Result::failure(Failure::validation('Dosage cannot be empty.'));
        }

        // Check idempotency if clientId is provided
        if ($command->clientId !== null && $command->clientId !== '') {
            $existing = $this->medicationRepository->findByClientId($command->clientId);
            if ($existing !== null) {
                return Result::success([
                    'id' => $existing->id()->value(),
                    'profileId' => $existing->profileId()->value(),
                    'name' => $existing->name(),
                    'dosage' => $existing->dosage(),
                    'instructions' => $existing->instructions(),
                    'photoUrl' => $existing->photoUrl(),
                    'clientId' => $existing->clientId(),
                    'form' => $existing->form(),
                    'colorToken' => $existing->colorToken(),
                    'createdAt' => $existing->createdAt()->format(\DateTimeInterface::ATOM),
                    'updatedAt' => $existing->updatedAt()->format(\DateTimeInterface::ATOM),
                ]);
            }
        }

        $medication = Medication::create(
            MedicationId::generate(),
            $profileId,
            $command->name,
            $command->dosage,
            $command->instructions,
            $command->photoUrl,
            $command->clientId,
            $command->form,
            $command->colorToken
        );

        $this->medicationRepository->save($medication);

        return Result::success([
            'id' => $medication->id()->value(),
            'profileId' => $medication->profileId()->value(),
            'name' => $medication->name(),
            'dosage' => $medication->dosage(),
            'instructions' => $medication->instructions(),
            'photoUrl' => $medication->photoUrl(),
            'clientId' => $medication->clientId(),
            'form' => $medication->form(),
            'colorToken' => $medication->colorToken(),
            'createdAt' => $medication->createdAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $medication->updatedAt()->format(\DateTimeInterface::ATOM),
        ]);
    }
}
