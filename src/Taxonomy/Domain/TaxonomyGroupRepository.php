<?php

declare(strict_types=1);

namespace Taxonomy\Domain;

use Shared\Domain\ValueObject\ProfileId;
use Shared\Domain\ValueObject\TaxonomyGroupId;

interface TaxonomyGroupRepository
{
    public function save(TaxonomyGroup $group): void;

    public function findById(TaxonomyGroupId $id): ?TaxonomyGroup;

    /**
     * @return TaxonomyGroup[]
     */
    public function findByProfileId(ProfileId $profileId): array;

    public function findByClientId(string $clientId): ?TaxonomyGroup;

    public function delete(TaxonomyGroup $group): void;
}
