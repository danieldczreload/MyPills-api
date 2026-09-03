<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use CalendarIntegration\Application\CalendarEventRemover;
use CalendarIntegration\Application\CalendarProviderResolver;
use CalendarIntegration\Application\Command\DisconnectCalendarCommand;
use CalendarIntegration\Application\Command\DisconnectCalendarHandler;
use CalendarIntegration\Application\Event\ScheduleDeletedHandler as CalendarScheduleDeletedHandler;
use CalendarIntegration\Domain\CalendarEventMapping;
use CalendarIntegration\Domain\CalendarEventMappingRepository;
use CalendarIntegration\Domain\CalendarLink;
use CalendarIntegration\Domain\CalendarLinkRepository;
use CalendarIntegration\Domain\CalendarOAuthTokens;
use CalendarIntegration\Domain\CalendarProvider;
use DoseEvent\Application\Event\ScheduleDeletedHandler as DoseEventScheduleDeletedHandler;
use DoseEvent\Domain\DoseEvent;
use DoseEvent\Domain\DoseEventRepository;
use Medication\Domain\Medication;
use Medication\Domain\MedicationRepository;
use Notification\Application\Command\CancelRecurringNotificationsCommand;
use Notification\Application\Command\CancelRecurringNotificationsHandler;
use Notification\Domain\DeviceTokenRepository;
use Notification\Domain\PushNotificationGateway;
use Profile\Domain\PatientProfile;
use Profile\Domain\ProfileRepository;
use Schedule\Domain\DailySchedule;
use Schedule\Domain\ScheduleDeletedEvent;
use Schedule\Domain\ScheduleRepository;
use Schedule\Domain\ValueObject\TimeOfDay;
use Shared\Domain\TokenVault;
use Shared\Domain\ValueObject\DoseEventId;
use Shared\Domain\ValueObject\MedicationId;
use Shared\Domain\ValueObject\ProfileId;
use Shared\Domain\ValueObject\ScheduleId;
use Shared\Domain\ValueObject\UserId;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class LeftoverCalendarMappingLocatabilityTest extends KernelTestCase
{
    public function testCancelRecurringFiveXxKeepsMappingVisibleToDisconnect(): void
    {
        self::bootKernel();
        $fx = $this->seedPendingDoseWithMapping();

        $google = $this->failingGoogleGateway();
        $remover = new CalendarEventRemover(
            $fx['linkRepo'],
            $fx['mapRepo'],
            new CalendarProviderResolver($google, $this->createMock(CalendarProvider::class)),
            $fx['vault']
        );

        $handler = new CancelRecurringNotificationsHandler(
            $fx['profileRepo'],
            $fx['medRepo'],
            $fx['schedRepo'],
            $fx['doseRepo'],
            $fx['deviceRepo'],
            $fx['pushGateway'],
            $fx['mapRepo'],
            $remover
        );

        $result = $handler(new CancelRecurringNotificationsCommand(
            $fx['profileId']->value(),
            $fx['accountId']->value(),
            $fx['scheduleId']->value(),
            medicationId: null,
            cancelPush: true,
            cancelCalendar: true,
            deleteSchedule: false
        ));

        self::assertTrue($result->isSuccess());
        self::assertSame(0, $result->getValue()['calendarEventsDeleted']);

        $kept = $fx['mapRepo']->findByProfileAndProvider($fx['profileId'], 'google');
        self::assertCount(1, $kept);
        self::assertSame($fx['mapping']->id(), $kept[0]->id());

        $dose = $fx['doseRepo']->findById($fx['doseId']);
        self::assertNotNull($dose);
        self::assertSame('skipped', $dose->status());

        $disconnect = new DisconnectCalendarHandler($fx['linkRepo'], $fx['profileRepo'], $fx['mapRepo'], $remover);
        $disconnectResult = $disconnect(new DisconnectCalendarCommand(
            $fx['profileId']->value(),
            $fx['accountId']->value(),
            'google'
        ));
        self::assertTrue($disconnectResult->isFailure());
        self::assertSame('CALENDAR_DISCONNECT_FAILED', $disconnectResult->getFailure()->getType());
        self::assertNotNull($fx['linkRepo']->findByProfileAndProvider($fx['profileId'], 'google'));
    }

    public function testScheduleDeletedThenPendingDeleteKeepsMappingVisibleToDisconnect(): void
    {
        self::bootKernel();
        $fx = $this->seedPendingDoseWithMapping();

        $google = $this->failingGoogleGateway();
        $remover = new CalendarEventRemover(
            $fx['linkRepo'],
            $fx['mapRepo'],
            new CalendarProviderResolver($google, $this->createMock(CalendarProvider::class)),
            $fx['vault']
        );

        $event = new ScheduleDeletedEvent($fx['scheduleId']->value(), $fx['profileId']->value());
        $calendarHandler = new CalendarScheduleDeletedHandler($fx['mapRepo'], $fx['doseRepo'], $remover);
        $doseHandler = new DoseEventScheduleDeletedHandler($fx['doseRepo']);
        $calendarHandler($event);
        $doseHandler($event);

        $kept = $fx['mapRepo']->findByProfileAndProvider($fx['profileId'], 'google');
        self::assertCount(1, $kept);
        self::assertSame($fx['mapping']->id(), $kept[0]->id());

        $dose = $fx['doseRepo']->findById($fx['doseId']);
        self::assertNotNull($dose);
        self::assertSame('skipped', $dose->status());

        $disconnect = new DisconnectCalendarHandler($fx['linkRepo'], $fx['profileRepo'], $fx['mapRepo'], $remover);
        $disconnectResult = $disconnect(new DisconnectCalendarCommand(
            $fx['profileId']->value(),
            $fx['accountId']->value(),
            'google'
        ));
        self::assertTrue($disconnectResult->isFailure());
        self::assertSame('CALENDAR_DISCONNECT_FAILED', $disconnectResult->getFailure()->getType());
        self::assertNotNull($fx['linkRepo']->findByProfileAndProvider($fx['profileId'], 'google'));
    }

    /**
     * @return array{
     *     profileId: ProfileId,
     *     accountId: UserId,
     *     scheduleId: ScheduleId,
     *     doseId: DoseEventId,
     *     mapping: CalendarEventMapping,
     *     profileRepo: ProfileRepository,
     *     medRepo: MedicationRepository,
     *     schedRepo: ScheduleRepository,
     *     doseRepo: DoseEventRepository,
     *     deviceRepo: DeviceTokenRepository,
     *     pushGateway: PushNotificationGateway,
     *     mapRepo: CalendarEventMappingRepository,
     *     linkRepo: CalendarLinkRepository,
     *     vault: TokenVault
     * }
     */
    private function seedPendingDoseWithMapping(): array
    {
        $container = static::getContainer();

        /** @var ProfileRepository $profileRepo */
        $profileRepo = $container->get(ProfileRepository::class);
        /** @var MedicationRepository $medRepo */
        $medRepo = $container->get(MedicationRepository::class);
        /** @var ScheduleRepository $schedRepo */
        $schedRepo = $container->get(ScheduleRepository::class);
        /** @var DoseEventRepository $doseRepo */
        $doseRepo = $container->get(DoseEventRepository::class);
        /** @var DeviceTokenRepository $deviceRepo */
        $deviceRepo = $container->get(DeviceTokenRepository::class);
        /** @var PushNotificationGateway $pushGateway */
        $pushGateway = $container->get(PushNotificationGateway::class);
        /** @var CalendarEventMappingRepository $mapRepo */
        $mapRepo = $container->get(CalendarEventMappingRepository::class);
        /** @var CalendarLinkRepository $linkRepo */
        $linkRepo = $container->get(CalendarLinkRepository::class);
        /** @var TokenVault $vault */
        $vault = $container->get(TokenVault::class);

        $accountId = UserId::generate();
        $profileId = ProfileId::generate();
        $profileRepo->save(PatientProfile::create(
            $profileId,
            $accountId,
            'Locatability Patient',
            new \DateTimeImmutable('1990-01-01'),
            'female'
        ));

        $medicationId = MedicationId::generate();
        $medRepo->save(Medication::create($medicationId, $profileId, 'Aspirin', '100mg', null));

        $now = new \DateTimeImmutable();
        $scheduleId = ScheduleId::generate();
        $schedRepo->save(new DailySchedule(
            $scheduleId,
            $medicationId,
            [new TimeOfDay(8, 0)],
            $now,
            null,
            null,
            $now,
            $now
        ));

        $doseId = DoseEventId::generate();
        $doseRepo->save(DoseEvent::create($doseId, $medicationId, $scheduleId, $now->modify('+1 day')));

        $mapping = CalendarEventMapping::create($doseId->value(), 'google', 'ext-locatability-1');
        $mapRepo->save($mapping);

        $linkRepo->save(CalendarLink::create($profileId, 'google', $vault->encrypt('refresh-token-secret')));

        return [
            'profileId' => $profileId,
            'accountId' => $accountId,
            'scheduleId' => $scheduleId,
            'doseId' => $doseId,
            'mapping' => $mapping,
            'profileRepo' => $profileRepo,
            'medRepo' => $medRepo,
            'schedRepo' => $schedRepo,
            'doseRepo' => $doseRepo,
            'deviceRepo' => $deviceRepo,
            'pushGateway' => $pushGateway,
            'mapRepo' => $mapRepo,
            'linkRepo' => $linkRepo,
            'vault' => $vault,
        ];
    }

    private function failingGoogleGateway(): CalendarProvider
    {
        $google = $this->createMock(CalendarProvider::class);
        $google->method('refreshAccessToken')->willReturn(new CalendarOAuthTokens('access-token', null));
        $google->method('deleteEvent')->willThrowException(new \RuntimeException('Google Calendar API delete failed with status 500.'));

        return $google;
    }
}
