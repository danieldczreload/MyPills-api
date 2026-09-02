<?php

declare(strict_types=1);

namespace Medication\Application\Query;

use Medication\Application\MedicationView;
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
     * @return Result<array<array{id: string, profileId: string, name: string, instructions: ?string, photoUrl: ?string, clientId: ?string, form: string, colorToken: string, createdAt: string, updatedAt: string}>>
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

        $data = array_map(MedicationView::toArray(...), $medications);

        return Result::success($data);
    }
}
