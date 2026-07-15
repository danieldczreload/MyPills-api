<?php

declare(strict_types=1);

namespace Profile\Domain;

use Shared\Domain\ValueObject\ProfileId;

interface TombstoneRepository
{
    public function save(Tombstone $tombstone): void;

    /**
     * @return Tombstone[]
     */
    public function findByProfileIdSince(ProfileId $profileId, \DateTimeImmutable $since): array;
}
