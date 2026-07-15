<?php

declare(strict_types=1);

namespace Profile\Domain;

use Shared\Domain\ValueObject\ProfileId;
use Shared\Domain\ValueObject\UserId;

interface ProfileRepository
{
    public function save(PatientProfile $profile): void;

    public function findById(ProfileId $id): ?PatientProfile;

    /**
     * @return PatientProfile[]
     */
    public function findByAccountId(UserId $accountId): array;

    public function delete(PatientProfile $profile): void;
}
