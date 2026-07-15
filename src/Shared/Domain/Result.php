<?php

declare(strict_types=1);

namespace Shared\Domain;

/**
 * @template-covariant T
 */
final class Result
{
    /**
     * @param T|null $value
     */
    private function __construct(
        private readonly bool $isSuccess,
        private readonly mixed $value = null,
        private readonly ?Failure $failure = null
    ) {
    }

    /**
     * @template U
     * @param U $value
     * @return Result<U>
     */
    public static function success(mixed $value = null): self
    {
        return new self(true, $value);
    }

    /**
     * @return Result<never>
     */
    public static function failure(Failure $failure): self
    {
        /** @var Result<never> $result */
        $result = new self(false, null, $failure);
        return $result;
    }

    public function isSuccess(): bool
    {
        return $this->isSuccess;
    }

    public function isFailure(): bool
    {
        return !$this->isSuccess;
    }

    /**
     * @return T
     */
    public function getValue(): mixed
    {
        if (!$this->isSuccess) {
            throw new \LogicException('Cannot get value of a failed result.');
        }

        /** @var T $val */
        $val = $this->value;

        return $val;
    }

    public function getFailure(): Failure
    {
        if ($this->isSuccess || $this->failure === null) {
            throw new \LogicException('Cannot get failure of a successful result.');
        }

        return $this->failure;
    }
}
