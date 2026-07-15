<?php

declare(strict_types=1);

namespace Profile\Domain;

use Shared\Domain\ValueObject\ProfileId;

final class Tombstone
{
    public function __construct(
        private readonly string $id,
        private readonly ProfileId $profileId,
        private readonly string $entityType,
        private readonly string $entityId,
        private readonly \DateTimeImmutable $deletedAt
    ) {
    }

    public static function create(
        ProfileId $profileId,
        string $entityType,
        string $entityId
    ): self {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));

        return new self($uuid, $profileId, $entityType, $entityId, new \DateTimeImmutable());
    }

    public function id(): string
    {
        return $this->id;
    }

    public function profileId(): ProfileId
    {
        return $this->profileId;
    }

    public function entityType(): string
    {
        return $this->entityType;
    }

    public function entityId(): string
    {
        return $this->entityId;
    }

    public function deletedAt(): \DateTimeImmutable
    {
        return $this->deletedAt;
    }
}
