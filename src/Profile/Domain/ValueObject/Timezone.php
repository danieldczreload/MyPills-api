<?php

declare(strict_types=1);

namespace Profile\Domain\ValueObject;

final readonly class Timezone
{
    public function __construct(
        private string $value
    ) {
        if (!in_array($this->value, timezone_identifiers_list(), true)) {
            throw new \InvalidArgumentException(sprintf('Timezone "%s" is not a valid IANA identifier.', $this->value));
        }
    }

    public function value(): string
    {
        return $this->value;
    }

    public function toDateTimeZone(): \DateTimeZone
    {
        return new \DateTimeZone($this->value);
    }

    public static function tryParse(string $value): ?self
    {
        try {
            return new self($value);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    public static function dateTimeZoneOrUtc(string $value): \DateTimeZone
    {
        return self::tryParse($value)?->toDateTimeZone() ?? new \DateTimeZone('UTC');
    }

    public function startOfDay(\DateTimeImmutable $date): \DateTimeImmutable
    {
        return new \DateTimeImmutable($date->format('Y-m-d'), $this->toDateTimeZone());
    }

    public function endOfDay(\DateTimeImmutable $date): \DateTimeImmutable
    {
        return $this->startOfDay($date)->setTime(23, 59, 59);
    }
}
