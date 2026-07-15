<?php

declare(strict_types=1);

namespace Schedule\Domain\ValueObject;

final class TimeOfDay
{
    public function __construct(
        private readonly int $hour,
        private readonly int $minute
    ) {
        if ($this->hour < 0 || $this->hour > 23) {
            throw new \InvalidArgumentException('Hour must be between 0 and 23.');
        }
        if ($this->minute < 0 || $this->minute > 59) {
            throw new \InvalidArgumentException('Minute must be between 0 and 59.');
        }
    }

    public function hour(): int
    {
        return $this->hour;
    }

    public function minute(): int
    {
        return $this->minute;
    }

    public function equals(self $other): bool
    {
        return $this->hour === $other->hour && $this->minute === $other->minute;
    }

    public function toString(): string
    {
        return sprintf('%02d:%02d', $this->hour, $this->minute);
    }

    public static function fromString(string $time): self
    {
        $parts = explode(':', $time);
        if (count($parts) !== 2) {
            throw new \InvalidArgumentException('Time must be in hh:mm format.');
        }
        return new self((int) $parts[0], (int) $parts[1]);
    }
}
