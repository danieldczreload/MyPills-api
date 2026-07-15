<?php

declare(strict_types=1);

namespace Identity\Domain;

final class ExternalUser
{
    public function __construct(
        public readonly string $externalId,
        public readonly string $email,
        public readonly string $name
    ) {
    }
}
