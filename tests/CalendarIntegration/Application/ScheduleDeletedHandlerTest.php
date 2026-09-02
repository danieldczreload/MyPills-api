<?php

declare(strict_types=1);

namespace App\Tests\CalendarIntegration\Application;

use CalendarIntegration\Application\CalendarEventRemover;
use CalendarIntegration\Application\CalendarProviderResolver;
use CalendarIntegration\Application\Event\ScheduleDeletedHandler;
use CalendarIntegration\Domain\CalendarEventMapping;
use CalendarIntegration\Domain\CalendarEventMappingRepository;
use CalendarIntegration\Domain\CalendarLink;
use CalendarIntegration\Domain\CalendarLinkRepository;
use CalendarIntegration\Domain\CalendarOAuthTokens;
use CalendarIntegration\Domain\CalendarProvider;
use DoseEvent\Domain\DoseEvent;
use DoseEvent\Domain\DoseEventRepository;
use PHPUnit\Framework\TestCase;
use Schedule\Domain\ScheduleDeletedEvent;
use Shared\Domain\TokenVault;
use Shared\Domain\ValueObject\DoseEventId;
use Shared\Domain\ValueObject\MedicationId;
use Shared\Domain\ValueObject\ProfileId;
use Shared\Domain\ValueObject\ScheduleId;

final class ScheduleDeletedHandlerTest extends TestCase
{
    public function testDeletesCalendarEventsWhenScheduleDeleted(): void
    {
        $profileId = new ProfileId('00000000-0000-0000-0000-000000000001');
        $scheduleId = new ScheduleId('00000000-0000-0000-0000-000000000002');
        $medicationId = new MedicationId('00000000-0000-0000-0000-000000000003');

        $dose1 = DoseEvent::create(new DoseEventId('00000000-0000-0000-0000-000000000011'), $medicationId, $scheduleId, new \DateTimeImmutable('+1 day'));

        $doseRepo = $this->createMock(DoseEventRepository::class);
        $doseRepo->method('findByScheduleId')->with($scheduleId)->willReturn([$dose1]);

        $map1 = CalendarEventMapping::create($dose1->id()->value(), 'google', 'ext-1');
        $mapRepo = $this->createMock(CalendarEventMappingRepository::class);
        $mapRepo->method('findByDoseEventIds')->with([$dose1->id()->value()])->willReturn([$map1]);
        $mapRepo->expects(self::once())->method('delete')->with($map1);
        $mapRepo->expects(self::once())->method('flush');

        $link = CalendarLink::create($profileId, 'google', 'enc-refresh');
        $linkRepo = $this->createMock(CalendarLinkRepository::class);
        $linkRepo->method('findByProfileAndProvider')->with($profileId, 'google')->willReturn($link);

        $google = $this->createMock(CalendarProvider::class);
        $google->method('refreshAccessToken')->with('dec-refresh')->willReturn(new CalendarOAuthTokens('google-tok', null));
        $google->expects(self::once())->method('deleteEvent')->with('google-tok', 'ext-1');

        $microsoft = $this->createMock(CalendarProvider::class);
        $resolver = new CalendarProviderResolver($google, $microsoft);

        $tokenVault = $this->createMock(TokenVault::class);
        $tokenVault->method('decrypt')->with('enc-refresh')->willReturn('dec-refresh');

        $handler = new ScheduleDeletedHandler(
            $mapRepo,
            $doseRepo,
            new CalendarEventRemover($linkRepo, $mapRepo, $resolver, $tokenVault)
        );

        $handler(new ScheduleDeletedEvent($scheduleId->value(), $profileId->value()));
    }

    public function testKeepsMappingWhenRemoteDeleteFails(): void
    {
        $profileId = new ProfileId('00000000-0000-0000-0000-000000000001');
        $scheduleId = new ScheduleId('00000000-0000-0000-0000-000000000002');
        $medicationId = new MedicationId('00000000-0000-0000-0000-000000000003');

        $dose1 = DoseEvent::create(new DoseEventId('00000000-0000-0000-0000-000000000011'), $medicationId, $scheduleId, new \DateTimeImmutable('+1 day'));

        $doseRepo = $this->createMock(DoseEventRepository::class);
        $doseRepo->method('findByScheduleId')->willReturn([$dose1]);

        $map1 = CalendarEventMapping::create($dose1->id()->value(), 'google', 'ext-1');
        $mapRepo = $this->createMock(CalendarEventMappingRepository::class);
        $mapRepo->method('findByDoseEventIds')->willReturn([$map1]);
        $mapRepo->expects(self::never())->method('delete');
        $mapRepo->expects(self::once())->method('flush');

        $link = CalendarLink::create($profileId, 'google', 'enc-refresh');
        $linkRepo = $this->createMock(CalendarLinkRepository::class);
        $linkRepo->method('findByProfileAndProvider')->willReturn($link);

        $google = $this->createMock(CalendarProvider::class);
        $google->method('refreshAccessToken')->willReturn(new CalendarOAuthTokens('google-tok', null));
        $google->method('deleteEvent')->willThrowException(new \RuntimeException('Google Calendar API delete failed with status 500.'));

        $tokenVault = $this->createMock(TokenVault::class);
        $tokenVault->method('decrypt')->willReturn('dec-refresh');

        $handler = new ScheduleDeletedHandler(
            $mapRepo,
            $doseRepo,
            new CalendarEventRemover(
                $linkRepo,
                $mapRepo,
                new CalendarProviderResolver($google, $this->createMock(CalendarProvider::class)),
                $tokenVault
            )
        );

        $handler(new ScheduleDeletedEvent($scheduleId->value(), $profileId->value()));
    }

    public function testReturnsEarlyWhenNoDoseEvents(): void
    {
        $doseRepo = $this->createMock(DoseEventRepository::class);
        $doseRepo->method('findByScheduleId')->willReturn([]);
        $mapRepo = $this->createMock(CalendarEventMappingRepository::class);
        $mapRepo->expects(self::never())->method('findByDoseEventIds');

        $handler = new ScheduleDeletedHandler(
            $mapRepo,
            $doseRepo,
            new CalendarEventRemover(
                $this->createMock(CalendarLinkRepository::class),
                $mapRepo,
                new CalendarProviderResolver($this->createMock(CalendarProvider::class), $this->createMock(CalendarProvider::class)),
                $this->createMock(TokenVault::class)
            )
        );

        $handler(new ScheduleDeletedEvent(
            '00000000-0000-0000-0000-000000000002',
            '00000000-0000-0000-0000-000000000001'
        ));
    }
}
