<?php

declare(strict_types=1);

namespace CalendarIntegration\Domain;

final class CalendarEventMapping
{
    public function __construct(
        private readonly string $id,
        private readonly string $doseEventId,
        private readonly string $provider,
        private readonly string $externalEventId,
        private readonly \DateTimeImmutable $createdAt
    ) {
    }

    public static function create(
        string $doseEventId,
        string $provider,
        string $externalEventId
    ): self {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));

        return new self($uuid, $doseEventId, $provider, $externalEventId, new \DateTimeImmutable());
    }

    public function id(): string
    {
        return $this->id;
    }

    public function doseEventId(): string
    {
        return $this->doseEventId;
    }

    public function provider(): string
    {
        return $this->provider;
    }

    public function externalEventId(): string
    {
        return $this->externalEventId;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
