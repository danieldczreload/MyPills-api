<?php

declare(strict_types=1);

namespace Medication\Application\Query;

use Medication\Domain\MedicationRepository;
use Profile\Domain\ProfileRepository;
use Shared\Domain\Result;
use Shared\Domain\Failure;
use Shared\Domain\ValueObject\ProfileId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GetMedicationsHandler
{
    public function __construct(
        private readonly MedicationRepository $medicationRepository,
        private readonly ProfileRepository $profileRepository
    ) {
    }

    /**
     * @return Result<array<array{id: string, profileId: string, name: string, dosage: string, instructions: ?string, photoUrl: ?string, clientId: ?string, form: string, colorToken: string, createdAt: string, updatedAt: string}>>
     */
    public function __invoke(GetMedicationsQuery $query): Result
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

        $data = array_map(static function ($medication) {
            return [
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
            ];
        }, $medications);

        return Result::success($data);
    }
}
