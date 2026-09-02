<?php

declare(strict_types=1);

namespace Profile\Application\Command;

final class UpdateProfileCommand
{
    public function __construct(
        public readonly string $id,
        public readonly string $accountId,
        public readonly string $name,
        public readonly \DateTimeImmutable $birthDate,
        public readonly string $gender,
        public readonly ?string $photoUrl = null,
        public readonly ?string $timezone = null
    ) {
    }
}
