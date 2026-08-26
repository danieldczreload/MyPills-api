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
use Notification\Application\Command\CancelRecurringNotificationsCommand;
use Notification\Application\Command\CancelRecurringNotificationsHandler;
use Notification\Domain\DeviceToken;
use Notification\Domain\DeviceTokenRepository;
use Notification\Domain\PushNotificationGateway;
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

final class CancelRecurringNotificationsHandlerTest extends TestCase
{
    public function testCancelsRecurringNotificationsForSchedule(): void
    {
        $profileId = new ProfileId('00000000-0000-0000-0000-000000000001');
        $accountId = new UserId('00000000-0000-0000-0000-000000000002');
        $medicationId = new MedicationId('00000000-0000-0000-0000-000000000003');
        $scheduleId = new ScheduleId('00000000-0000-0000-0000-000000000004');

        $profile = PatientProfile::create($profileId, $accountId, 'Test', new \DateTimeImmutable('1990-01-01'), 'male');

        $medication = Medication::create($medicationId, $profileId, 'Amoxicillin', '500mg', 'After meals');
        $now = new \DateTimeImmutable();
        $schedule = new DailySchedule($scheduleId, $medicationId, [new TimeOfDay(8, 0)], $now, null, null, $now, $now);

        $dose1 = DoseEvent::create(new DoseEventId('00000000-0000-0000-0000-000000000011'), $medicationId, $scheduleId, $now->modify('+1 day'));
        $dose2 = DoseEvent::create(new DoseEventId('00000000-0000-0000-0000-000000000012'), $medicationId, $scheduleId, $now->modify('+2 days'));

        $profileRepo = $this->createMock(ProfileRepository::class);
        $profileRepo->method('findById')->with($profileId)->willReturn($profile);

        $medicationRepo = $this->createMock(MedicationRepository::class);
        $medicationRepo->method('findById')->with($medicationId)->willReturn($medication);

        $schedRepo = $this->createMock(ScheduleRepository::class);
        $schedRepo->method('findById')->with($scheduleId)->willReturn($schedule);
        $schedRepo->expects(self::once())->method('delete')->with($schedule);

        $doseRepo = $this->createMock(DoseEventRepository::class);
        $doseRepo->method('findPendingByScheduleIds')->with([$scheduleId])->willReturn([$dose1, $dose2]);
        $doseRepo->expects(self::once())->method('deletePendingByScheduleIds')->with([$scheduleId]);

        $device = DeviceToken::create($accountId, 'fcm-tok-123', 'android', 'es');
        $deviceRepo = $this->createMock(DeviceTokenRepository::class);
        $deviceRepo->method('findByAccountId')->with($accountId)->willReturn([$device]);

        $pushGateway = $this->createMock(PushNotificationGateway::class);
        $pushGateway->expects(self::once())->method('send')->with(
            'fcm-tok-123',
            'Recordatorios recurrentes cancelados',
            'Se han cancelado los recordatorios recurrentes programados.',
            self::callback(function (array $data) use ($profileId, $scheduleId): bool {
                return ($data['type'] ?? '') === 'cancel_recurring'
                    && ($data['profileId'] ?? '') === $profileId->value()
                    && ($data['scheduleId'] ?? '') === $scheduleId->value()
                    && ($data['cancelledDosesCount'] ?? '') === '2';
            })
        );

        $link = CalendarLink::create($profileId, 'google', 'enc-refresh');
        $linkRepo = $this->createMock(CalendarLinkRepository::class);
        $linkRepo->method('findByProfileAndProvider')->with($profileId, 'google')->willReturn($link);

        $map1 = CalendarEventMapping::create($dose1->id()->value(), 'google', 'ext-1');
        $map2 = CalendarEventMapping::create($dose2->id()->value(), 'google', 'ext-2');
        $mapRepo = $this->createMock(CalendarEventMappingRepository::class);
        $mapRepo->method('findByDoseEventIds')->with([$dose1->id()->value(), $dose2->id()->value()])->willReturn([$map1, $map2]);
        $mapRepo->expects(self::exactly(2))->method('delete');
        $mapRepo->expects(self::once())->method('flush');

        $google = $this->createMock(CalendarProvider::class);
        $google->method('refreshAccessToken')->with('dec-refresh')->willReturn(new CalendarOAuthTokens('google-token', null));
        $google->expects(self::exactly(2))->method('deleteEvent');

        $microsoft = $this->createMock(CalendarProvider::class);
        $resolver = new CalendarProviderResolver($google, $microsoft);

        $tokenVault = $this->createMock(TokenVault::class);
        $tokenVault->method('decrypt')->with('enc-refresh')->willReturn('dec-refresh');

        $handler = new CancelRecurringNotificationsHandler(
            $profileRepo,
            $medicationRepo,
            $schedRepo,
            $doseRepo,
            $deviceRepo,
            $pushGateway,
            $linkRepo,
            $mapRepo,
            $resolver,
            $tokenVault
        );

        $command = new CancelRecurringNotificationsCommand(
            $profileId->value(),
            $accountId->value(),
            $scheduleId->value(),
            medicationId: null,
            cancelPush: true,
            cancelCalendar: true,
            deleteSchedule: true
        );

        $result = $handler($command);

        self::assertTrue($result->isSuccess());
        /** @var array<string, mixed> $data */
        $data = $result->getValue();
        self::assertSame(1, $data['schedulesTargeted']);
        self::assertSame(2, $data['pendingDosesCancelled']);
        self::assertSame(2, $data['calendarEventsDeleted']);
        self::assertTrue($data['pushCancelled']);
    }
}
