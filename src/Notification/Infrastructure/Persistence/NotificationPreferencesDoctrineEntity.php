<?php

declare(strict_types=1);

namespace Notification\Infrastructure\Persistence;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'notification_preferences')]
class NotificationPreferencesDoctrineEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36, unique: true)]
    private string $id;

    #[ORM\Column(type: 'string', length: 36, unique: true)]
    private string $accountId;

    #[ORM\Column(type: 'boolean')]
    private bool $doseRemindersEnabled;

    #[ORM\Column(type: 'boolean')]
    private bool $missedDoseNudgesEnabled;

    #[ORM\Column(type: 'boolean')]
    private bool $refillAlertsEnabled;

    #[ORM\Column(type: 'boolean')]
    private bool $weeklyStreakSummariesEnabled;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        string $id,
        string $accountId,
        bool $doseRemindersEnabled,
        bool $missedDoseNudgesEnabled,
        bool $refillAlertsEnabled,
        bool $weeklyStreakSummariesEnabled,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt
    ) {
        $this->id = $id;
        $this->accountId = $accountId;
        $this->doseRemindersEnabled = $doseRemindersEnabled;
        $this->missedDoseNudgesEnabled = $missedDoseNudgesEnabled;
        $this->refillAlertsEnabled = $refillAlertsEnabled;
        $this->weeklyStreakSummariesEnabled = $weeklyStreakSummariesEnabled;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getAccountId(): string
    {
        return $this->accountId;
    }

    public function isDoseRemindersEnabled(): bool
    {
        return $this->doseRemindersEnabled;
    }

    public function setDoseRemindersEnabled(bool $doseRemindersEnabled): void
    {
        $this->doseRemindersEnabled = $doseRemindersEnabled;
    }

    public function isMissedDoseNudgesEnabled(): bool
    {
        return $this->missedDoseNudgesEnabled;
    }

    public function setMissedDoseNudgesEnabled(bool $missedDoseNudgesEnabled): void
    {
        $this->missedDoseNudgesEnabled = $missedDoseNudgesEnabled;
    }

    public function isRefillAlertsEnabled(): bool
    {
        return $this->refillAlertsEnabled;
    }

    public function setRefillAlertsEnabled(bool $refillAlertsEnabled): void
    {
        $this->refillAlertsEnabled = $refillAlertsEnabled;
    }

    public function isWeeklyStreakSummariesEnabled(): bool
    {
        return $this->weeklyStreakSummariesEnabled;
    }

    public function setWeeklyStreakSummariesEnabled(bool $weeklyStreakSummariesEnabled): void
    {
        $this->weeklyStreakSummariesEnabled = $weeklyStreakSummariesEnabled;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}
