<?php

declare(strict_types=1);

namespace Notification\Domain;

use Shared\Domain\ValueObject\UserId;

final class NotificationPreferences
{
    public function __construct(
        private readonly string $id,
        private readonly UserId $accountId,
        private bool $doseRemindersEnabled,
        private bool $missedDoseNudgesEnabled,
        private bool $refillAlertsEnabled,
        private bool $weeklyStreakSummariesEnabled,
        private readonly \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt
    ) {
    }

    public static function createDefault(UserId $accountId): self
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));

        $now = new \DateTimeImmutable();
        return new self($uuid, $accountId, true, true, true, true, $now, $now);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function accountId(): UserId
    {
        return $this->accountId;
    }

    public function doseRemindersEnabled(): bool
    {
        return $this->doseRemindersEnabled;
    }

    public function missedDoseNudgesEnabled(): bool
    {
        return $this->missedDoseNudgesEnabled;
    }

    public function refillAlertsEnabled(): bool
    {
        return $this->refillAlertsEnabled;
    }

    public function weeklyStreakSummariesEnabled(): bool
    {
        return $this->weeklyStreakSummariesEnabled;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function update(
        bool $doseRemindersEnabled,
        bool $missedDoseNudgesEnabled,
        bool $refillAlertsEnabled,
        bool $weeklyStreakSummariesEnabled
    ): void {
        $this->doseRemindersEnabled = $doseRemindersEnabled;
        $this->missedDoseNudgesEnabled = $missedDoseNudgesEnabled;
        $this->refillAlertsEnabled = $refillAlertsEnabled;
        $this->weeklyStreakSummariesEnabled = $weeklyStreakSummariesEnabled;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
