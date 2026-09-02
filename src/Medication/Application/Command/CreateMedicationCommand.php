<?php

declare(strict_types=1);

namespace Medication\Application\Command;

final class CreateMedicationCommand
{
    public function __construct(
        public readonly string $profileId,
        public readonly string $accountId,
        public readonly string $name,
        public readonly ?string $instructions = null,
        public readonly ?string $photoUrl = null,
        public readonly ?string $clientId = null,
        public readonly string $form = 'pill',
        public readonly string $colorToken = 'sky'
    ) {
    }
}
