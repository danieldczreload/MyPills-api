<?php

declare(strict_types=1);

namespace Medication\Domain;

use Shared\Domain\ValueObject\MedicationId;
use Shared\Domain\ValueObject\ProfileId;

interface MedicationRepository
{
    public function save(Medication $medication): void;

    public function findById(MedicationId $id): ?Medication;

    /**
     * @return Medication[]
     */
    public function findByProfileId(ProfileId $profileId): array;

    public function findByClientId(string $clientId): ?Medication;

    public function delete(Medication $medication): void;
}
