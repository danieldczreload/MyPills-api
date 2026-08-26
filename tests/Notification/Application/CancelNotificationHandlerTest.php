<?php

declare(strict_types=1);

namespace App\Tests\Notification\Application;

use CalendarIntegration\Application\CalendarProviderResolver;
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
use Notification\Application\Command\CancelNotificationCommand;
use Notification\Application\Command\CancelNotificationHandler;
use Notification\Domain\DeviceToken;
use Notification\Domain\DeviceTokenRepository;
use Notification\Domain\PushNotificationGateway;
use PHPUnit\Framework\TestCase;
use Profile\Domain\PatientProfile;
use Profile\Domain\ProfileRepository;
use Shared\Domain\TokenVault;
use Shared\Domain\ValueObject\DoseEventId;
use Shared\Domain\ValueObject\MedicationId;
use Shared\Domain\ValueObject\ProfileId;
use Shared\Domain\ValueObject\ScheduleId;
use Shared\Domain\ValueObject\UserId;

final class CancelNotificationHandlerTest extends TestCase
{
    public function testCancelsIndividualNotificationPushAndCalendar(): void
    {
        $profileId = new ProfileId('00000000-0000-0000-0000-000000000001');
        $accountId = new UserId('00000000-0000-0000-0000-000000000002');
        $medicationId = new MedicationId('00000000-0000-0000-0000-000000000003');
        $scheduleId = new ScheduleId('00000000-0000-0000-0000-000000000004');
        $doseEventId = new DoseEventId('00000000-0000-0000-0000-000000000005');

        $profile = PatientProfile::create(
            $profileId,
            $accountId,
            'Test Patient',
            new \DateTimeImmutable('1990-01-01'),
            'female'
        );


        $medication = Medication::create(
            $medicationId,
            $profileId,
            'Paracetamol',
            '500mg',
            'With water'
        );

        $doseEvent = DoseEvent::create(
            $doseEventId,
            $medicationId,
            $scheduleId,
            new \DateTimeImmutable('+2 hours')
        );

        $profileRepo = $this->createMock(ProfileRepository::class);
        $profileRepo->method('findById')->with($profileId)->willReturn($profile);

        $medicationRepo = $this->createMock(MedicationRepository::class);
        $medicationRepo->method('findById')->with($medicationId)->willReturn($medication);

        $doseRepo = $this->createMock(DoseEventRepository::class);
        $doseRepo->method('findById')->with($doseEventId)->willReturn($doseEvent);
        $doseRepo->expects(self::once())->method('save')->with(self::callback(function (DoseEvent $d): bool {
            return $d->status() === 'skipped';
        }));

        $device = DeviceToken::create($accountId, 'test-fcm-token-1', 'android', 'es');
        $deviceRepo = $this->createMock(DeviceTokenRepository::class);
        $deviceRepo->method('findByAccountId')->with($accountId)->willReturn([$device]);

        $pushGateway = $this->createMock(PushNotificationGateway::class);
        $pushGateway->expects(self::once())->method('send')->with(
            'test-fcm-token-1',
            'Recordatorio cancelado',
            'El recordatorio de Paracetamol ha sido cancelado.',
            self::callback(function (array $data) use ($doseEventId, $scheduleId, $profileId, $medicationId): bool {
                return ($data['type'] ?? '') === 'cancel_notification'
                    && ($data['doseEventId'] ?? '') === $doseEventId->value()
                    && ($data['scheduleId'] ?? '') === $scheduleId->value()
                    && ($data['profileId'] ?? '') === $profileId->value()
                    && ($data['medicationId'] ?? '') === $medicationId->value();
            })
        );

        $link = CalendarLink::create($profileId, 'google', 'enc-refresh-token');
        $linkRepo = $this->createMock(CalendarLinkRepository::class);
        $linkRepo->method('findByProfileAndProvider')->with($profileId, 'google')->willReturn($link);

        $mapping = CalendarEventMapping::create($doseEventId->value(), 'google', 'ext-event-123');
        $mapRepo = $this->createMock(CalendarEventMappingRepository::class);
        $mapRepo->method('findByDoseEventId')->with($doseEventId->value())->willReturn([$mapping]);
        $mapRepo->expects(self::once())->method('delete')->with($mapping);
        $mapRepo->expects(self::once())->method('flush');

        $google = $this->createMock(CalendarProvider::class);
        $google->method('refreshAccessToken')->with('dec-refresh-token')->willReturn(new CalendarOAuthTokens('google-access-token', null));
        $google->expects(self::once())->method('deleteEvent')->with('google-access-token', 'ext-event-123');

        $microsoft = $this->createMock(CalendarProvider::class);
        $resolver = new CalendarProviderResolver($google, $microsoft);

        $tokenVault = $this->createMock(TokenVault::class);
        $tokenVault->method('decrypt')->with('enc-refresh-token')->willReturn('dec-refresh-token');

        $handler = new CancelNotificationHandler(
            $profileRepo,
            $medicationRepo,
            $doseRepo,
            $deviceRepo,
            $pushGateway,
            $linkRepo,
            $mapRepo,
            $resolver,
            $tokenVault
        );

        $command = new CancelNotificationCommand(
            $profileId->value(),
            $accountId->value(),
            $doseEventId->value(),
            cancelPush: true,
            cancelCalendar: true
        );

        $result = $handler($command);

        self::assertTrue($result->isSuccess());
        /** @var array<string, mixed> $data */
        $data = $result->getValue();
        self::assertSame($doseEventId->value(), $data['doseEventId']);
        self::assertSame('skipped', $data['status']);
        self::assertTrue($data['pushCancelled']);
        self::assertSame(1, $data['calendarEventsDeleted']);
    }

    public function testFailsWhenProfileNotFoundOrNotOwned(): void
    {
        $profileRepo = $this->createMock(ProfileRepository::class);
        $profileRepo->method('findById')->willReturn(null);

        $handler = new CancelNotificationHandler(
            $profileRepo,
            $this->createMock(MedicationRepository::class),
            $this->createMock(DoseEventRepository::class),
            $this->createMock(DeviceTokenRepository::class),
            $this->createMock(PushNotificationGateway::class),
            $this->createMock(CalendarLinkRepository::class),
            $this->createMock(CalendarEventMappingRepository::class),
            new CalendarProviderResolver($this->createMock(CalendarProvider::class), $this->createMock(CalendarProvider::class)),
            $this->createMock(TokenVault::class)
        );

        $res = $handler(new CancelNotificationCommand('prof-1', 'acc-1', 'dose-1'));
        self::assertTrue($res->isFailure());
        self::assertSame('Profile not found.', $res->getFailure()->getMessage());
    }

    public function testFailsWhenDoseEventNotFound(): void
    {
        $profileId = new ProfileId('00000000-0000-0000-0000-000000000001');
        $accountId = new UserId('00000000-0000-0000-0000-000000000002');
        $profile = PatientProfile::create($profileId, $accountId, 'Test', new \DateTimeImmutable('1990-01-01'), 'female');

        $profileRepo = $this->createMock(ProfileRepository::class);
        $profileRepo->method('findById')->willReturn($profile);

        $doseRepo = $this->createMock(DoseEventRepository::class);
        $doseRepo->method('findById')->willReturn(null);

        $handler = new CancelNotificationHandler(
            $profileRepo,
            $this->createMock(MedicationRepository::class),
            $doseRepo,
            $this->createMock(DeviceTokenRepository::class),
            $this->createMock(PushNotificationGateway::class),
            $this->createMock(CalendarLinkRepository::class),
            $this->createMock(CalendarEventMappingRepository::class),
            new CalendarProviderResolver($this->createMock(CalendarProvider::class), $this->createMock(CalendarProvider::class)),
            $this->createMock(TokenVault::class)
        );

        $res = $handler(new CancelNotificationCommand($profileId->value(), $accountId->value(), 'dose-1'));
        self::assertTrue($res->isFailure());
        self::assertSame('Dose event not found.', $res->getFailure()->getMessage());
    }
}
