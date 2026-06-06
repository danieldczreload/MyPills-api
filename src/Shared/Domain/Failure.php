<?php

declare(strict_types=1);

namespace Shared\Domain;

final class Failure
{
    /**
     * @param array<string, mixed> $details
     */
    private function __construct(
        private readonly string $type,
        private readonly string $message,
        private readonly array $details = []
    ) {
    }

    /**
     * @param array<string, mixed> $details
     */
    public static function notFound(string $message, array $details = []): self
    {
        return new self('NOT_FOUND', $message, $details);
    }

    /**
     * @param array<string, mixed> $details
     */
    public static function validation(string $message, array $details = []): self
    {
        return new self('VALIDATION', $message, $details);
    }

    /**
     * @param array<string, mixed> $details
     */
    public static function unauthorized(string $message, array $details = []): self
    {
        return new self('UNAUTHORIZED', $message, $details);
    }

    /**
     * @param array<string, mixed> $details
     */
    public static function forbidden(string $message, array $details = []): self
    {
        return new self('FORBIDDEN', $message, $details);
    }

    /**
     * @param array<string, mixed> $details
     */
    public static function conflict(string $message, array $details = []): self
    {
        return new self('CONFLICT', $message, $details);
    }

    /**
     * @param array<string, mixed> $details
     */
    public static function badRequest(string $message, array $details = []): self
    {
        return new self('BAD_REQUEST', $message, $details);
    }

    /**
     * @param array<string, mixed> $details
     */
    public static function server(string $message, array $details = []): self
    {
        return new self('SERVER_ERROR', $message, $details);
    }

    /**
     * @param array<string, mixed> $details
     */
    public static function custom(string $type, string $message, array $details = []): self
    {
        return new self($type, $message, $details);
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDetails(): array
    {
        return $this->details;
    }
}
