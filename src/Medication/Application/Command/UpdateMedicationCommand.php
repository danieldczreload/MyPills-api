<?php

declare(strict_types=1);

namespace Medication\Application\Command;

final class UpdateMedicationCommand
{
    public function __construct(
        public readonly string $id,
        public readonly string $profileId,
        public readonly string $accountId,
        public readonly string $name,
        public readonly string $dosage,
        public readonly ?string $instructions = null,
        public readonly ?string $photoUrl = null,
        public readonly string $form = 'pill',
        public readonly string $colorToken = 'sky'
    ) {
    }
}
