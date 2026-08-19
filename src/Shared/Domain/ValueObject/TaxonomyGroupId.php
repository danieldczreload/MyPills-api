<?php

declare(strict_types=1);

namespace Shared\Domain\ValueObject;

final class TaxonomyGroupId
{
    public function __construct(
        private readonly string $value
    ) {
        if ($this->value === '') {
            throw new \InvalidArgumentException('Taxonomy Group ID cannot be empty.');
        }
    }

    public static function generate(): self
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));

        return new self($uuid);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
