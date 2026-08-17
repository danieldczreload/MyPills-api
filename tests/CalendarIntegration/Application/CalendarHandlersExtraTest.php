<?php

declare(strict_types=1);

namespace App\Tests\CalendarIntegration\Application;

use CalendarIntegration\Application\CalendarProviderResolver;
use CalendarIntegration\Application\Command\DisconnectCalendarCommand;
use CalendarIntegration\Application\Command\DisconnectCalendarHandler;
use CalendarIntegration\Application\Command\SyncCalendarCommand;
use CalendarIntegration\Application\Command\SyncCalendarHandler;
use CalendarIntegration\Domain\CalendarAuthorizationRevoked;
use CalendarIntegration\Domain\CalendarEventMapping;
use CalendarIntegration\Domain\CalendarEventMappingRepository;
use CalendarIntegration\Domain\CalendarLink;
use CalendarIntegration\Domain\CalendarLinkRepository;
use CalendarIntegration\Domain\CalendarProvider;
use DoseEvent\Domain\DoseEvent;
use DoseEvent\Domain\DoseEventRepository;
use Medication\Domain\Medication;
use Medication\Domain\MedicationRepository;
use PHPUnit\Framework\TestCase;
use Profile\Domain\PatientProfile;
use Profile\Domain\ProfileRepository;
use Schedule\Domain\DailySchedule;
use Schedule\Domain\ScheduleRepository;
use Schedule\Domain\ValueObject\TimeOfDay;
use Shared\Domain\TokenVault;
use Shared\Domain\ValueObject\DoseEventId;
use Shared\Domain\ValueObject\MedicationId;
use Shared\Domain\ValueObject\ProfileId;
use Shared\Domain\ValueObject\ScheduleId;
use Shared\Domain\ValueObject\UserId;

final class CalendarHandlersExtraTest extends TestCase
{
    public function testDisconnectCalendarWithEventsFailsToRefresh(): void
    {
        $linkRepo = $this->createMock(CalendarLinkRepository::class);
        $profileRepo = $this->createMock(ProfileRepository::class);
        $mapRepo = $this->createMock(CalendarEventMappingRepository::class);
        $google = $this->createMock(CalendarProvider::class);
        $microsoft = $this->createMock(CalendarProvider::class);
        $resolver = new CalendarProviderResolver($google, $microsoft);
        $vault = $this->createMock(TokenVault::class);

        $profile = new PatientProfile(new ProfileId('prof-1'), new UserId('acc-1'), 'Name', new \DateTimeImmutable('1990-01-01'), 'male', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $profileRepo->method('findById')->willReturn($profile);

        $link = CalendarLink::create(new ProfileId('prof-1'), 'google', 'enc-refresh');
        $linkRepo->method('findByProfileAndProvider')->willReturn($link);

        $mapping = CalendarEventMapping::create('dose-1', 'google', 'ext-1');
        $mapRepo->method('findByProfileAndProvider')->willReturn([$mapping]);

        $vault->method('decrypt')->willReturn('dec-refresh');
        $google->method('refreshAccessToken')->willThrowException(new \RuntimeException('Token invalid'));

        $linkRepo->expects(self::once())->method('save')->with($link);

        $handler = new DisconnectCalendarHandler($linkRepo, $profileRepo, $mapRepo, $resolver, $vault);
        $res = $handler(new DisconnectCalendarCommand('prof-1', 'acc-1', 'google'));

        self::assertTrue($res->isFailure());
        self::assertSame('CALENDAR_DISCONNECT_FAILED', $res->getFailure()->getType());
    }

    public function testSyncCalendarRevokedTriggersReauthRequired(): void
    {
        $linkRepo = $this->createMock(CalendarLinkRepository::class);
        $mapRepo = $this->createMock(CalendarEventMappingRepository::class);
        $profileRepo = $this->createMock(ProfileRepository::class);
        $medRepo = $this->createMock(MedicationRepository::class);
        $schedRepo = $this->createMock(ScheduleRepository::class);
        $doseRepo = $this->createMock(DoseEventRepository::class);
        $google = $this->createMock(CalendarProvider::class);
        $microsoft = $this->createMock(CalendarProvider::class);
        $resolver = new CalendarProviderResolver($google, $microsoft);
        $vault = $this->createMock(TokenVault::class);

        $profile = new PatientProfile(new ProfileId('prof-1'), new UserId('acc-1'), 'Name', new \DateTimeImmutable('1990-01-01'), 'male', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $profileRepo->method('findById')->willReturn($profile);

        $link = CalendarLink::create(new ProfileId('prof-1'), 'google', 'enc-refresh');
        $linkRepo->method('findByProfile')->willReturn([$link]);

        $medId = new MedicationId('med-1');
        $med = new Medication($medId, new ProfileId('prof-1'), 'Aspirin', '100mg', 'pill', 'instructions', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $medRepo->method('findByProfileId')->willReturn([$med]);

        $sched = new DailySchedule(new ScheduleId('sch-1'), $medId, [new TimeOfDay(8, 0)], new \DateTimeImmutable(), null, null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $schedRepo->method('findByMedicationIds')->willReturn([$sched]);

        $dose = DoseEvent::create(new DoseEventId('dose-1'), $medId, new ScheduleId('sch-1'), new \DateTimeImmutable('+1 day'));
        $doseRepo->method('findByScheduleIdsAndRange')->willReturn([$dose]);

        $vault->method('decrypt')->willReturn('dec-refresh');
        $google->method('refreshAccessToken')->willThrowException(new CalendarAuthorizationRevoked('Revoked'));

        $linkRepo->expects(self::once())->method('save');

        $handler = new SyncCalendarHandler(
            $linkRepo,
            $mapRepo,
            $profileRepo,
            $medRepo,
            $schedRepo,
            $doseRepo,
            $resolver,
            $vault
        );

        $res = $handler(new SyncCalendarCommand('acc-1', 'prof-1'));
        self::assertTrue($res->isFailure());
        self::assertSame('SYNC_PARTIAL_FAILURE', $res->getFailure()->getType());
    }
}
