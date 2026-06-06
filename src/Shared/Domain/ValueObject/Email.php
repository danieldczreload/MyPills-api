<?php

declare(strict_types=1);

namespace Shared\Domain\ValueObject;

final class Email
{
    public function __construct(
        private readonly string $value
    ) {
        if (filter_var($this->value, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException(sprintf('The email "%s" is invalid.', $this->value));
        }
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return strtolower($this->value) === strtolower($other->value);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
