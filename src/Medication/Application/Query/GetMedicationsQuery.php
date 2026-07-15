<?php

declare(strict_types=1);

namespace Medication\Application\Query;

final class GetMedicationsQuery
{
    public function __construct(
        public readonly string $profileId,
        public readonly string $accountId
    ) {
    }
}
