<?php

declare(strict_types=1);

namespace Profile\Application\Command;

final class DeleteProfileCommand
{
    public function __construct(
        public readonly string $id,
        public readonly string $accountId
    ) {
    }
}
