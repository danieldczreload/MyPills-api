<?php

declare(strict_types=1);

namespace Taxonomy\Application\Command;

final class DeleteTaxonomyGroupCommand
{
    public function __construct(
        public readonly string $id,
        public readonly string $profileId,
        public readonly string $accountId
    ) {
    }
}
