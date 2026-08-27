<?php

declare(strict_types=1);

namespace Notification\Infrastructure;

use Notification\Application\Command\DispatchDueRemindersCommand;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[AsSchedule(name: 'reminders')]
final class DoseReminderScheduleProvider implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())->add(
            RecurringMessage::every('60 seconds', new DispatchDueRemindersCommand())
        );
    }
}
