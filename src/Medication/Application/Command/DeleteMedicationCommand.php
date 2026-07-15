<?php

declare(strict_types=1);

namespace Medication\Application\Command;

final class DeleteMedicationCommand
{
    public function __construct(
        public readonly string $id,
        public readonly string $profileId,
        public readonly string $accountId
    ) {
    }
}
