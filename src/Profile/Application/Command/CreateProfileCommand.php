<?php

declare(strict_types=1);

namespace Profile\Application\Command;

final class CreateProfileCommand
{
    public function __construct(
        public readonly string $accountId,
        public readonly string $name,
        public readonly \DateTimeImmutable $birthDate,
        public readonly string $gender,
        public readonly ?string $photoUrl = null
    ) {
    }
}
