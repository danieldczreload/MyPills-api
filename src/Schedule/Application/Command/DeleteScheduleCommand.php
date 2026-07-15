<?php

declare(strict_types=1);

namespace Schedule\Application\Command;

final class DeleteScheduleCommand
{
    public function __construct(
        public readonly string $id,
        public readonly string $profileId,
        public readonly string $accountId
    ) {
    }
}
