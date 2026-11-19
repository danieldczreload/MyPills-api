<?php

declare(strict_types=1);

namespace Schedule\Domain;

use Shared\Domain\ValueObject\Dose;
use Shared\Domain\ValueObject\MedicationId;
use Shared\Domain\ValueObject\ScheduleId;
use Shared\Domain\ValueObject\Timezone;

abstract class Schedule
{
    protected readonly ?string $timezone;

    public function __construct(
        protected readonly ScheduleId $id,
        protected readonly MedicationId $medicationId,
        protected readonly \DateTimeImmutable $startDate,
        protected readonly ?\DateTimeImmutable $endDate,
        protected readonly ?string $clientId,
        protected readonly \DateTimeImmutable $createdAt,
        protected \DateTimeImmutable $updatedAt,
        protected readonly ?Dose $dose = null,
        protected ?\DateTimeImmutable $cancelledAt = null,
        ?string $timezone = null,
        protected bool $notifyAlarm = true,
        protected bool $notifyPush = false,
        protected bool $notifyCalendar = false
    ) {
        $this->timezone = self::normalizeTimezone($timezone);
    }

    public function id(): ScheduleId
    {
        return $this->id;
    }

    public function medicationId(): MedicationId
    {
        return $this->medicationId;
    }

    public function startDate(): \DateTimeImmutable
    {
        return $this->startDate;
    }

    public function endDate(): ?\DateTimeImmutable
    {
        return $this->endDate;
    }

    public function clientId(): ?string
    {
        return $this->clientId;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function dose(): ?Dose
    {
        return $this->dose;
    }

    public function cancelledAt(): ?\DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function timezone(): ?string
    {
        return $this->timezone;
    }

    public function notifyAlarm(): bool
    {
        return $this->notifyAlarm;
    }

    public function notifyPush(): bool
    {
        return $this->notifyPush;
    }

    public function notifyCalendar(): bool
    {
        return $this->notifyCalendar;
    }

    /**
     * Null means the schedule follows the profile zone.
     * A stored value is pinned and is not rebuilt when the profile zone changes.
     */
    public function followsProfileTimezone(): bool
    {
        return $this->timezone === null;
    }

    public function zone(\DateTimeZone $profileZone): \DateTimeZone
    {
        if ($this->timezone === null) {
            return $profileZone;
        }

        return new \DateTimeZone($this->timezone);
    }

    public function isCancelled(): bool
    {
        return $this->cancelledAt !== null;
    }

    public function markCancelled(\DateTimeImmutable $at): void
    {
        $this->cancelledAt = $at;
        $this->updatedAt = $at;
    }

    public function disableNotificationChannels(
        bool $disableAlarm,
        bool $disablePush,
        bool $disableCalendar,
        \DateTimeImmutable $at
    ): bool {
        $changed = false;

        if ($disableAlarm && $this->notifyAlarm) {
            $this->notifyAlarm = false;
            $changed = true;
        }
        if ($disablePush && $this->notifyPush) {
            $this->notifyPush = false;
            $changed = true;
        }
        if ($disableCalendar && $this->notifyCalendar) {
            $this->notifyCalendar = false;
            $changed = true;
        }

        if ($changed) {
            $this->updatedAt = $at;
        }

        return $changed;
    }

    abstract public function type(): string;

    private static function normalizeTimezone(?string $timezone): ?string
    {
        if ($timezone === null || $timezone === '') {
            return null;
        }

        return (new Timezone($timezone))->value();
    }
}
