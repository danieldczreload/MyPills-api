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
use CalendarIntegration\Domain\CalendarLinkStatus;
use CalendarIntegration\Domain\CalendarOAuthTokens;
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

    public function testSyncCalendarProfileNotFoundAndForbidden(): void
    {
        $linkRepo = $this->createMock(CalendarLinkRepository::class);
        $mapRepo = $this->createMock(CalendarEventMappingRepository::class);
        $profileRepo = $this->createMock(ProfileRepository::class);
        $medRepo = $this->createMock(MedicationRepository::class);
        $schedRepo = $this->createMock(ScheduleRepository::class);
        $doseRepo = $this->createMock(DoseEventRepository::class);
        $resolver = new CalendarProviderResolver($this->createMock(CalendarProvider::class), $this->createMock(CalendarProvider::class));
        $vault = $this->createMock(TokenVault::class);

        $handler = new SyncCalendarHandler($linkRepo, $mapRepo, $profileRepo, $medRepo, $schedRepo, $doseRepo, $resolver, $vault);

        // Not found
        $profileRepo->method('findById')->willReturn(null);
        $res = $handler(new SyncCalendarCommand('acc-1', 'prof-1'));
        self::assertTrue($res->isFailure());
        self::assertSame('Profile not found.', $res->getFailure()->getMessage());

        // Forbidden
        $otherProfile = new PatientProfile(new ProfileId('prof-1'), new UserId('acc-other'), 'Name', new \DateTimeImmutable('1990-01-01'), 'male', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $profileRepo = $this->createMock(ProfileRepository::class);
        $profileRepo->method('findById')->willReturn($otherProfile);
        $handler = new SyncCalendarHandler($linkRepo, $mapRepo, $profileRepo, $medRepo, $schedRepo, $doseRepo, $resolver, $vault);
        $res = $handler(new SyncCalendarCommand('acc-1', 'prof-1'));
        self::assertTrue($res->isFailure());
        self::assertSame('You do not own this profile.', $res->getFailure()->getMessage());
    }

    public function testSyncCalendarSkippedReasons(): void
    {
        $linkRepo = $this->createMock(CalendarLinkRepository::class);
        $mapRepo = $this->createMock(CalendarEventMappingRepository::class);
        $profileRepo = $this->createMock(ProfileRepository::class);
        $medRepo = $this->createMock(MedicationRepository::class);
        $schedRepo = $this->createMock(ScheduleRepository::class);
        $doseRepo = $this->createMock(DoseEventRepository::class);
        $resolver = new CalendarProviderResolver($this->createMock(CalendarProvider::class), $this->createMock(CalendarProvider::class));
        $vault = $this->createMock(TokenVault::class);

        $profile = new PatientProfile(new ProfileId('prof-1'), new UserId('acc-1'), 'Name', new \DateTimeImmutable('1990-01-01'), 'male', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $profileRepo->method('findByAccountId')->willReturn([$profile]);

        $link = CalendarLink::create(new ProfileId('prof-1'), 'google', 'enc-refresh');
        $linkRepo->method('findByProfile')->willReturn([$link]);

        // 1. No medications
        $medRepo->method('findByProfileId')->willReturn([]);
        $handler = new SyncCalendarHandler($linkRepo, $mapRepo, $profileRepo, $medRepo, $schedRepo, $doseRepo, $resolver, $vault);
        $res = $handler(new SyncCalendarCommand('acc-1', null));
        self::assertTrue($res->isSuccess());
        self::assertSame('NO_MEDICATIONS', $res->getValue()['skipped'][0]['reason']);

        // 2. No schedules
        $medId = new MedicationId('med-1');
        $med = new Medication($medId, new ProfileId('prof-1'), 'Aspirin', '100mg', 'pill', 'instructions', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $medRepo = $this->createMock(MedicationRepository::class);
        $medRepo->method('findByProfileId')->willReturn([$med]);
        $schedRepo->method('findByMedicationIds')->willReturn([]);
        $handler = new SyncCalendarHandler($linkRepo, $mapRepo, $profileRepo, $medRepo, $schedRepo, $doseRepo, $resolver, $vault);
        $res = $handler(new SyncCalendarCommand('acc-1', null));
        self::assertTrue($res->isSuccess());
        self::assertSame('NO_SCHEDULES', $res->getValue()['skipped'][0]['reason']);

        // 3. No dose events
        $sched = new DailySchedule(new ScheduleId('sch-1'), $medId, [new TimeOfDay(8, 0)], new \DateTimeImmutable(), null, null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $schedRepo = $this->createMock(ScheduleRepository::class);
        $schedRepo->method('findByMedicationIds')->willReturn([$sched]);
        $doseRepo->method('findByScheduleIdsAndRange')->willReturn([]);
        $handler = new SyncCalendarHandler($linkRepo, $mapRepo, $profileRepo, $medRepo, $schedRepo, $doseRepo, $resolver, $vault);
        $res = $handler(new SyncCalendarCommand('acc-1', null));
        self::assertTrue($res->isSuccess());
        self::assertSame('NO_UPCOMING_DOSE_EVENTS', $res->getValue()['skipped'][0]['reason']);
    }

    public function testSyncCalendarPerLinkFailureCasesAndMappingUpdate(): void
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

        $medId = new MedicationId('med-1');
        $med = new Medication($medId, new ProfileId('prof-1'), 'Aspirin', '100mg', 'pill', 'instructions', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $medRepo->method('findByProfileId')->willReturn([$med]);

        $sched = new DailySchedule(new ScheduleId('sch-1'), $medId, [new TimeOfDay(8, 0)], new \DateTimeImmutable(), null, null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $schedRepo->method('findByMedicationIds')->willReturn([$sched]);

        $dose = DoseEvent::create(new DoseEventId('dose-1'), $medId, new ScheduleId('sch-1'), new \DateTimeImmutable('+1 day'));
        $doseRepo->method('findByScheduleIdsAndRange')->willReturn([$dose]);

        // 1. Link marked REAUTH_REQUIRED recovers when refresh succeeds
        $reauthLink = CalendarLink::create(new ProfileId('prof-1'), 'google', 'enc-refresh');
        $reauthLink->markReauthorizationRequired();
        $linkRepo = $this->createMock(CalendarLinkRepository::class);
        $linkRepo->method('findByProfile')->willReturn([$reauthLink]);
        $vault->method('decrypt')->willReturn('dec-refresh');
        $google->method('refreshAccessToken')->willReturn(new CalendarOAuthTokens('access', null));
        $google->method('upsertEvent')->willReturn('ext-evt-1');
        $mapRepo->method('findByDoseEvents')->willReturn([]);
        $handler = new SyncCalendarHandler($linkRepo, $mapRepo, $profileRepo, $medRepo, $schedRepo, $doseRepo, $resolver, $vault);
        $res = $handler(new SyncCalendarCommand('acc-1', 'prof-1'));
        self::assertTrue($res->isSuccess());
        self::assertSame(CalendarLinkStatus::ACTIVE, $reauthLink->status());

        // 2. Unsupported provider
        $unknownLink = CalendarLink::create(new ProfileId('prof-1'), 'yahoo', 'enc-refresh');
        $linkRepo = $this->createMock(CalendarLinkRepository::class);
        $linkRepo->method('findByProfile')->willReturn([$unknownLink]);
        $handler = new SyncCalendarHandler($linkRepo, $mapRepo, $profileRepo, $medRepo, $schedRepo, $doseRepo, $resolver, $vault);
        $res = $handler(new SyncCalendarCommand('acc-1', 'prof-1'));
        self::assertTrue($res->isFailure());

        // 3. Generic Throwable on refresh
        $google = $this->createMock(CalendarProvider::class);
        $google->method('refreshAccessToken')->willThrowException(new \RuntimeException('Network timeout'));
        $resolver = new CalendarProviderResolver($google, $microsoft);
        $googleLink = CalendarLink::create(new ProfileId('prof-1'), 'google', 'enc-refresh');
        $linkRepo = $this->createMock(CalendarLinkRepository::class);
        $linkRepo->method('findByProfile')->willReturn([$googleLink]);
        $handler = new SyncCalendarHandler($linkRepo, $mapRepo, $profileRepo, $medRepo, $schedRepo, $doseRepo, $resolver, $vault);
        $res = $handler(new SyncCalendarCommand('acc-1', 'prof-1'));
        self::assertTrue($res->isFailure());

        // 4. Upsert failed with Throwable
        $google = $this->createMock(CalendarProvider::class);
        $google->method('refreshAccessToken')->willReturn(new \CalendarIntegration\Domain\CalendarOAuthTokens('access', 'refresh'));
        $google->method('upsertEvent')->willThrowException(new \RuntimeException('Google API 500'));
        $resolver = new CalendarProviderResolver($google, $microsoft);
        $handler = new SyncCalendarHandler($linkRepo, $mapRepo, $profileRepo, $medRepo, $schedRepo, $doseRepo, $resolver, $vault);
        $res = $handler(new SyncCalendarCommand('acc-1', 'prof-1'));
        self::assertTrue($res->isFailure());

        // 5. Existing mapping updated (externalEventId changed)
        $google = $this->createMock(CalendarProvider::class);
        $google->method('refreshAccessToken')->willReturn(new \CalendarIntegration\Domain\CalendarOAuthTokens('access', null));
        $google->method('upsertEvent')->willReturn('new-external-evt-id');
        $resolver = new CalendarProviderResolver($google, $microsoft);

        $existingMapping = CalendarEventMapping::create('dose-1', 'google', 'old-external-evt-id');
        $mapRepo = $this->createMock(CalendarEventMappingRepository::class);
        $mapRepo->method('findByDoseEvents')->willReturn(['dose-1:google' => $existingMapping]);
        $mapRepo->expects(self::once())->method('save')->with($existingMapping);
        $mapRepo->expects(self::once())->method('flush');

        $handler = new SyncCalendarHandler($linkRepo, $mapRepo, $profileRepo, $medRepo, $schedRepo, $doseRepo, $resolver, $vault);
        $res = $handler(new SyncCalendarCommand('acc-1', 'prof-1'));
        self::assertTrue($res->isSuccess());
        self::assertSame(1, $res->getValue()['eventsUpdated']);
    }

    public function testDisconnectCalendarForbiddenAndNotFound(): void
    {
        $linkRepo = $this->createMock(CalendarLinkRepository::class);
        $profileRepo = $this->createMock(ProfileRepository::class);
        $mapRepo = $this->createMock(CalendarEventMappingRepository::class);
        $google = $this->createMock(CalendarProvider::class);
        $microsoft = $this->createMock(CalendarProvider::class);
        $resolver = new CalendarProviderResolver($google, $microsoft);
        $vault = $this->createMock(TokenVault::class);

        // Forbidden
        $otherProfile = new PatientProfile(new ProfileId('prof-1'), new UserId('acc-other'), 'Name', new \DateTimeImmutable('1990-01-01'), 'male', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $profileRepo->method('findById')->willReturn($otherProfile);
        $handler = new DisconnectCalendarHandler($linkRepo, $profileRepo, $mapRepo, $resolver, $vault);
        $res = $handler(new DisconnectCalendarCommand('prof-1', 'acc-1', 'google'));
        self::assertTrue($res->isFailure());
        self::assertSame('You do not own this profile.', $res->getFailure()->getMessage());

        // Link not found
        $myProfile = new PatientProfile(new ProfileId('prof-1'), new UserId('acc-1'), 'Name', new \DateTimeImmutable('1990-01-01'), 'male', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $profileRepo = $this->createMock(ProfileRepository::class);
        $profileRepo->method('findById')->willReturn($myProfile);
        $linkRepo->method('findByProfileAndProvider')->willReturn(null);
        $handler = new DisconnectCalendarHandler($linkRepo, $profileRepo, $mapRepo, $resolver, $vault);
        $res = $handler(new DisconnectCalendarCommand('prof-1', 'acc-1', 'google'));
        self::assertTrue($res->isFailure());
        self::assertSame('NOT_FOUND', $res->getFailure()->getType());
    }
}
