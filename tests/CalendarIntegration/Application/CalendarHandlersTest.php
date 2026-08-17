<?php

declare(strict_types=1);

namespace App\Tests\CalendarIntegration\Application;

use CalendarIntegration\Application\CalendarProviderResolver;
use CalendarIntegration\Application\Command\CompleteCalendarAuthorizationCommand;
use CalendarIntegration\Application\Command\CompleteCalendarAuthorizationHandler;
use CalendarIntegration\Application\Command\DisconnectCalendarCommand;
use CalendarIntegration\Application\Command\DisconnectCalendarHandler;
use CalendarIntegration\Application\Command\StartCalendarAuthorizationCommand;
use CalendarIntegration\Application\Command\StartCalendarAuthorizationHandler;
use CalendarIntegration\Application\Command\SyncCalendarCommand;
use CalendarIntegration\Application\Command\SyncCalendarHandler;
use CalendarIntegration\Domain\CalendarAuthorizationRequest;
use CalendarIntegration\Domain\CalendarAuthorizationRequestRepository;
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

final class CalendarHandlersTest extends TestCase
{
    public function testStartCalendarAuthorizationValidationAndSuccess(): void
    {
        $authRepo = $this->createMock(CalendarAuthorizationRequestRepository::class);
        $profileRepo = $this->createMock(ProfileRepository::class);
        $google = $this->createMock(CalendarProvider::class);
        $microsoft = $this->createMock(CalendarProvider::class);
        $resolver = new CalendarProviderResolver($google, $microsoft);

        $handler = new StartCalendarAuthorizationHandler($authRepo, $profileRepo, $resolver);

        // Invalid provider
        $res = $handler(new StartCalendarAuthorizationCommand('prof-1', 'acc-1', 'invalid', str_repeat('c', 43)));
        self::assertTrue($res->isFailure());

        // Invalid challenge
        $res = $handler(new StartCalendarAuthorizationCommand('prof-1', 'acc-1', 'google', 'short'));
        self::assertTrue($res->isFailure());

        // Profile not found
        $profileRepo->method('findById')->willReturn(null);
        $res = $handler(new StartCalendarAuthorizationCommand('prof-1', 'acc-1', 'google', str_repeat('c', 43)));
        self::assertTrue($res->isFailure());

        // Success
        $profile = new PatientProfile(new ProfileId('prof-1'), new UserId('acc-1'), 'Name', new \DateTimeImmutable('1990-01-01'), 'male', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $profileRepo = $this->createMock(ProfileRepository::class);
        $profileRepo->method('findById')->willReturn($profile);
        $google->method('authorizationUrl')->willReturn('https://accounts.google.com/o/oauth2/v2/auth?client_id=123');
        $authRepo->expects(self::once())->method('save');

        $handler = new StartCalendarAuthorizationHandler($authRepo, $profileRepo, $resolver);
        $res = $handler(new StartCalendarAuthorizationCommand('prof-1', 'acc-1', 'google', str_repeat('c', 43)));
        self::assertTrue($res->isSuccess());
        self::assertArrayHasKey('authorizationUrl', $res->getValue());
    }

    public function testCompleteCalendarAuthorizationValidationAndSuccess(): void
    {
        $authRepo = $this->createMock(CalendarAuthorizationRequestRepository::class);
        $linkRepo = $this->createMock(CalendarLinkRepository::class);
        $profileRepo = $this->createMock(ProfileRepository::class);
        $vault = $this->createMock(TokenVault::class);
        $google = $this->createMock(CalendarProvider::class);
        $microsoft = $this->createMock(CalendarProvider::class);
        $resolver = new CalendarProviderResolver($google, $microsoft);

        $handler = new CompleteCalendarAuthorizationHandler($authRepo, $linkRepo, $profileRepo, $vault, $resolver);

        // Validation failures
        $res = $handler(new CompleteCalendarAuthorizationCommand('prof-1', 'acc-1', 'invalid', 'code', 'state', str_repeat('v', 43)));
        self::assertTrue($res->isFailure());

        $res = $handler(new CompleteCalendarAuthorizationCommand('prof-1', 'acc-1', 'google', '', 'state', str_repeat('v', 43)));
        self::assertTrue($res->isFailure());

        // Profile found, state verified, exchange code
        $profile = new PatientProfile(new ProfileId('prof-1'), new UserId('acc-1'), 'Name', new \DateTimeImmutable('1990-01-01'), 'male', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $profileRepo->method('findById')->willReturn($profile);

        $codeVerifier = str_repeat('v', 43);
        $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
        $authReq = CalendarAuthorizationRequest::create(
            new UserId('acc-1'),
            new ProfileId('prof-1'),
            'google',
            hash('sha256', 'state-1'),
            $codeChallenge,
            new \DateTimeImmutable('+5 minutes')
        );
        $authRepo->method('findByStateHash')->willReturn($authReq);
        $authRepo->method('consume')->willReturn(true);

        $google->method('exchangeAuthorizationCode')->willReturn(new CalendarOAuthTokens('access-tok', 'refresh-tok'));
        $vault->method('encrypt')->willReturn('enc-refresh-tok');
        $linkRepo->expects(self::once())->method('save');

        $res = $handler(new CompleteCalendarAuthorizationCommand('prof-1', 'acc-1', 'google', 'code-1', 'state-1', $codeVerifier));
        self::assertTrue($res->isSuccess());
        self::assertTrue($res->getValue()['connected']);
    }

    public function testDisconnectCalendarSuccessAndErrors(): void
    {
        $linkRepo = $this->createMock(CalendarLinkRepository::class);
        $profileRepo = $this->createMock(ProfileRepository::class);
        $mapRepo = $this->createMock(CalendarEventMappingRepository::class);
        $google = $this->createMock(CalendarProvider::class);
        $microsoft = $this->createMock(CalendarProvider::class);
        $resolver = new CalendarProviderResolver($google, $microsoft);
        $vault = $this->createMock(TokenVault::class);

        $handler = new DisconnectCalendarHandler($linkRepo, $profileRepo, $mapRepo, $resolver, $vault);

        // Not found
        $profileRepo->method('findById')->willReturn(null);
        $res = $handler(new DisconnectCalendarCommand('prof-1', 'acc-1', 'google'));
        self::assertTrue($res->isFailure());

        // Link found and deleted
        $profile = new PatientProfile(new ProfileId('prof-1'), new UserId('acc-1'), 'Name', new \DateTimeImmutable('1990-01-01'), 'male', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $profileRepo = $this->createMock(ProfileRepository::class);
        $profileRepo->method('findById')->willReturn($profile);

        $link = CalendarLink::create(new ProfileId('prof-1'), 'google', 'enc-refresh');
        $linkRepo->method('findByProfileAndProvider')->willReturn($link);
        $mapRepo->method('findByProfileAndProvider')->willReturn([]);
        $linkRepo->expects(self::once())->method('delete')->with($link);

        $handler = new DisconnectCalendarHandler($linkRepo, $profileRepo, $mapRepo, $resolver, $vault);
        $res = $handler(new DisconnectCalendarCommand('prof-1', 'acc-1', 'google'));
        self::assertTrue($res->isSuccess());
    }

    public function testSyncCalendarSuccess(): void
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

        $google->method('refreshAccessToken')->willReturn(new CalendarOAuthTokens('access-1', 'new-refresh-1'));
        $google->method('upsertEvent')->willReturn('ext-evt-1');
        $vault->method('decrypt')->willReturn('dec-refresh');
        $vault->method('encrypt')->willReturn('enc-new-refresh');

        $mapRepo->method('findByDoseEvents')->willReturn([]);
        $mapRepo->expects(self::once())->method('save');
        $mapRepo->expects(self::once())->method('flush');

        $res = $handler(new SyncCalendarCommand('acc-1', 'prof-1'));
        self::assertTrue($res->isSuccess());
    }
}
