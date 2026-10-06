<?php

declare(strict_types=1);

namespace Shared\Domain\ValueObject;

final readonly class Timezone
{
    public function __construct(
        private string $value
    ) {
        if (in_array($this->value, timezone_identifiers_list(), true) || self::isPosixGmtOffset($this->value)) {
            return;
        }

        throw new \InvalidArgumentException(sprintf('Timezone "%s" is not a valid IANA identifier.', $this->value));
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

    /**
     * The default identifier list omits POSIX Etc/GMT±N links. Android sends Etc/GMT+6 for a fixed UTC−6 offset.
     */
    private static function isPosixGmtOffset(string $value): bool
    {
        if (preg_match('/^Etc\/GMT[+-]\d{1,2}$/', $value) !== 1) {
            return false;
        }

        try {
            new \DateTimeZone($value);

            return true;
        } catch (\Exception) {
            return false;
        }
    }
}
