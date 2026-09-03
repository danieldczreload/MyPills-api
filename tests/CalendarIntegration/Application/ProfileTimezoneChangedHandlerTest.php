<?php

declare(strict_types=1);

namespace App\Tests\CalendarIntegration\Application;

use CalendarIntegration\Application\CalendarEventRemover;
use CalendarIntegration\Application\CalendarProviderResolver;
use CalendarIntegration\Application\Event\ProfileTimezoneChangedHandler;
use CalendarIntegration\Domain\CalendarEventMapping;
use CalendarIntegration\Domain\CalendarEventMappingRepository;
use CalendarIntegration\Domain\CalendarLink;
use CalendarIntegration\Domain\CalendarLinkRepository;
use CalendarIntegration\Domain\CalendarOAuthTokens;
use CalendarIntegration\Domain\CalendarProvider;
use DoseEvent\Domain\DoseEvent;
use DoseEvent\Domain\DoseEventRepository;
use Medication\Domain\Medication;
use Medication\Domain\MedicationRepository;
use PHPUnit\Framework\TestCase;
use Profile\Domain\ProfileTimezoneChangedEvent;
use Schedule\Domain\DailySchedule;
use Schedule\Domain\ScheduleRepository;
use Schedule\Domain\ValueObject\TimeOfDay;
use Shared\Domain\TokenVault;
use Shared\Domain\ValueObject\DoseEventId;
use Shared\Domain\ValueObject\MedicationId;
use Shared\Domain\ValueObject\ProfileId;
use Shared\Domain\ValueObject\ScheduleId;

final class ProfileTimezoneChangedHandlerTest extends TestCase
{
    public function testRemovesCalendarEventsForPendingDoses(): void
    {
        $profileId = new ProfileId('prof-1');
        $medicationId = new MedicationId('med-1');
        $scheduleId = new ScheduleId('sch-1');
        $doseId = new DoseEventId('dose-1');

        $medicationRepo = $this->createMock(MedicationRepository::class);
        $medicationRepo->method('findByProfileId')->willReturn([
            Medication::create($medicationId, $profileId, 'Aspirin'),
        ]);
        $scheduleRepo = $this->createMock(ScheduleRepository::class);
        $scheduleRepo->method('findByMedicationIds')->willReturn([
            new DailySchedule($scheduleId, $medicationId, [new TimeOfDay(8, 0)], new \DateTimeImmutable(), null, null, new \DateTimeImmutable(), new \DateTimeImmutable()),
        ]);
        $dose = DoseEvent::create($doseId, $medicationId, $scheduleId, new \DateTimeImmutable('+1 day'));
        $doseRepo = $this->createMock(DoseEventRepository::class);
        $doseRepo->method('findPendingByScheduleIds')->willReturn([$dose]);
        $doseRepo->expects(self::never())->method('save');

        $mapping = CalendarEventMapping::create($doseId->value(), 'google', 'ext-1');
        $mapRepo = $this->createMock(CalendarEventMappingRepository::class);
        $mapRepo->method('findByDoseEventIds')->willReturnOnConsecutiveCalls([$mapping], []);
        $mapRepo->expects(self::once())->method('delete');
        $mapRepo->expects(self::once())->method('flush');

        $link = CalendarLink::create($profileId, 'google', 'enc-refresh');
        $linkRepo = $this->createMock(CalendarLinkRepository::class);
        $linkRepo->method('findByProfileAndProvider')->willReturn($link);

        $google = $this->createMock(CalendarProvider::class);
        $google->method('refreshAccessToken')->willReturn(new CalendarOAuthTokens('tok', null));
        $google->expects(self::once())->method('deleteEvent')->with('tok', 'ext-1');
        $tokenVault = $this->createMock(TokenVault::class);
        $tokenVault->method('decrypt')->willReturn('dec');

        $handler = new ProfileTimezoneChangedHandler(
            $medicationRepo,
            $scheduleRepo,
            $doseRepo,
            $mapRepo,
            new CalendarEventRemover($linkRepo, $mapRepo, new CalendarProviderResolver($google, $this->createMock(CalendarProvider::class)), $tokenVault)
        );
        $handler(new ProfileTimezoneChangedEvent('prof-1', 'UTC', 'America/El_Salvador'));
    }
}
