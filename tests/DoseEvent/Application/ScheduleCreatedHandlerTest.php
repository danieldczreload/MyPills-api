<?php

declare(strict_types=1);

namespace App\Tests\DoseEvent\Application;

use DoseEvent\Application\Event\ScheduleCreatedHandler;
use DoseEvent\Domain\DoseEventExpander;
use DoseEvent\Domain\DoseEventRepository;
use DoseEvent\Domain\DoseEventsExpandedEvent;
use PHPUnit\Framework\TestCase;
use Schedule\Domain\DailySchedule;
use Schedule\Domain\ScheduleCreatedEvent;
use Schedule\Domain\ScheduleRepository;
use Schedule\Domain\ValueObject\TimeOfDay;
use Shared\Application\Bus\EventBus;
use Shared\Domain\ValueObject\MedicationId;
use Shared\Domain\ValueObject\ScheduleId;

final class ScheduleCreatedHandlerTest extends TestCase
{
    public function testDoesNothingWhenScheduleIsMissing(): void
    {
        $scheduleRepo = $this->createMock(ScheduleRepository::class);
        $doseRepo = $this->createMock(DoseEventRepository::class);
        $eventBus = $this->createMock(EventBus::class);

        $scheduleRepo->method('findById')->willReturn(null);
        $doseRepo->expects(self::never())->method('save');
        $eventBus->expects(self::never())->method('publish');

        $handler = new ScheduleCreatedHandler(
            $scheduleRepo,
            $doseRepo,
            new DoseEventExpander(),
            $eventBus
        );

        $handler(new ScheduleCreatedEvent('sch-1', 'med-1', 'prof-1'));
    }

    public function testPublishesDoseEventsExpandedWhenOccurrencesAreCreated(): void
    {
        $scheduleId = new ScheduleId('sch-1');
        $medicationId = new MedicationId('med-1');
        $schedule = new DailySchedule(
            $scheduleId,
            $medicationId,
            [new TimeOfDay(8, 0)],
            new \DateTimeImmutable('-1 day'),
            null,
            null,
            new \DateTimeImmutable(),
            new \DateTimeImmutable()
        );

        $scheduleRepo = $this->createMock(ScheduleRepository::class);
        $scheduleRepo->method('findById')->willReturn($schedule);

        $doseRepo = $this->createMock(DoseEventRepository::class);
        $doseRepo->method('findByScheduleIdsAndRange')->willReturn([]);
        $doseRepo->expects(self::atLeastOnce())->method('save');

        $eventBus = $this->createMock(EventBus::class);
        $eventBus->expects(self::once())->method('publish')->with(self::callback(
            static function (object $event): bool {
                return $event instanceof DoseEventsExpandedEvent
                    && $event->profileId === 'prof-1'
                    && $event->scheduleId === 'sch-1';
            }
        ));

        $handler = new ScheduleCreatedHandler(
            $scheduleRepo,
            $doseRepo,
            new DoseEventExpander(),
            $eventBus
        );

        $handler(new ScheduleCreatedEvent('sch-1', 'med-1', 'prof-1'));
    }
}
