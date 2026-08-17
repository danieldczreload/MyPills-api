<?php

declare(strict_types=1);

namespace Notification\Domain;

interface DueDoseReminderRepository
{
    /**
     * @return DueDoseReminder[]
     */
    public function findDueDoseReminders(\DateTimeImmutable $now): array;
}
