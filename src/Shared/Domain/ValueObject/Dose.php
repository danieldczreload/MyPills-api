<?php

declare(strict_types=1);

namespace Shared\Domain\ValueObject;

final class Dose
{
    public function __construct(
        private readonly string $amount,
        private readonly DoseUnit $unit
    ) {
        if (preg_match('/^(?:0|[1-9]\d{0,7})(?:\.\d{1,4})?$/', $this->amount) !== 1) {
            throw new \InvalidArgumentException('Dose amount must be a positive number with up to 4 decimal places.');
        }

        if ((float) $this->amount <= 0) {
            throw new \InvalidArgumentException('Dose amount must be greater than zero.');
        }
    }

    public static function of(int|float|string $amount, string $unit): self
    {
        return new self(self::normalizeInput($amount), DoseUnit::fromInput($unit));
    }

    public function amount(): string
    {
        return $this->normalizedAmount();
    }

    public function amountAsNumber(): int|float
    {
        $amount = $this->normalizedAmount();
        if (!str_contains($amount, '.')) {
            return (int) $amount;
        }

        return (float) $amount;
    }

    public function unit(): DoseUnit
    {
        return $this->unit;
    }

    public function display(): string
    {
        $amount = $this->normalizedAmount();

        return $amount . ' ' . $this->unit->displayLabel($amount);
    }

    /**
     * @return array{amount: int|float, unit: string, display: string}
     */
    public function toApiArray(): array
    {
        return [
            'amount' => $this->amountAsNumber(),
            'unit' => $this->unit->value,
            'display' => $this->display(),
        ];
    }

    /**
     * FCM data payload values must be strings.
     *
     * @return array{doseDisplay: string, doseAmount: string, doseUnit: string}
     */
    public function toPushData(): array
    {
        return [
            'doseDisplay' => $this->display(),
            'doseAmount' => $this->amount(),
            'doseUnit' => $this->unit->value,
        ];
    }

    /**
     * @return array{doseDisplay: string, doseAmount: string, doseUnit: string}
     */
    public static function emptyPushData(): array
    {
        return [
            'doseDisplay' => '',
            'doseAmount' => '',
            'doseUnit' => '',
        ];
    }

    public function equals(self $other): bool
    {
        return $this->normalizedAmount() === $other->normalizedAmount()
            && $this->unit === $other->unit;
    }

    public static function tryFromStorage(?string $amount, ?string $unit): ?self
    {
        if ($amount === null || $unit === null || trim($amount) === '' || trim($unit) === '') {
            return null;
        }

        $normalizedAmount = self::normalizeInput($amount);

        try {
            return new self($normalizedAmount, DoseUnit::fromInput($unit));
        } catch (\ValueError|\InvalidArgumentException) {
            return null;
        }
    }

    public static function normalizeInput(int|float|string $amount): string
    {
        if (is_int($amount)) {
            return (string) $amount;
        }

        if (is_float($amount)) {
            $formatted = rtrim(rtrim(sprintf('%.4F', $amount), '0'), '.');

            return $formatted === '' ? '0' : $formatted;
        }

        $trimmed = trim($amount);
        if (str_contains($trimmed, '.')) {
            $trimmed = rtrim(rtrim($trimmed, '0'), '.');
        }

        return $trimmed === '' ? '0' : $trimmed;
    }

    private function normalizedAmount(): string
    {
        return self::normalizeInput($this->amount);
    }
}
