<?php

declare(strict_types=1);

namespace App\Tests\Notification\Infrastructure;

use DoseEvent\Application\Command\ExpandDoseEventsCommand;
use Notification\Application\Command\DispatchDueRemindersCommand;
use Notification\Infrastructure\DoseReminderScheduleProvider;
use PHPUnit\Framework\TestCase;

final class DoseReminderScheduleProviderTest extends TestCase
{
    public function testScheduleIncludesHourlyDoseExpansion(): void
    {
        $descriptions = [];
        foreach ((new DoseReminderScheduleProvider())->getSchedule()->getRecurringMessages() as $recurring) {
            $provider = $recurring->getProvider();
            if (!$provider instanceof \Stringable) {
                self::fail('Schedule message provider must be stringable.');
            }
            $descriptions[] = $provider->__toString();
        }

        self::assertCount(2, $descriptions);
        self::assertStringContainsString(DispatchDueRemindersCommand::class, $descriptions[0]);
        self::assertStringContainsString(ExpandDoseEventsCommand::class, $descriptions[1]);
    }
}
