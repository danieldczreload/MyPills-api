<?php

declare(strict_types=1);

namespace Taxonomy\Application\Query;

final class GetTaxonomyGroupsQuery
{
    public function __construct(
        public readonly string $profileId,
        public readonly string $accountId
    ) {
    }
}
