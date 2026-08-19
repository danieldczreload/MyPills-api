<?php

declare(strict_types=1);

namespace Taxonomy\Application\Command;

final class UpdateTaxonomyGroupCommand
{
    public function __construct(
        public readonly string $id,
        public readonly string $profileId,
        public readonly string $accountId,
        public readonly string $type,
        public readonly string $name,
        public readonly ?string $description = null,
        public readonly ?string $iconName = null,
        public readonly ?int $colorValue = null,
        public readonly bool $isCustom = true
    ) {
    }
}
