<?php

declare(strict_types=1);

namespace App\Tests\Notification\Application;

use DoseEvent\Domain\DoseEvent;
use DoseEvent\Domain\DoseEventRepository;
use Notification\Application\Command\SendDoseReminderCommand;
use Notification\Application\Command\SendDoseReminderHandler;
use Notification\Domain\DeviceToken;
use Notification\Domain\DeviceTokenRepository;
use Notification\Domain\PushNotificationGateway;
use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\Dose;
use Shared\Domain\ValueObject\DoseEventId;
use Shared\Domain\ValueObject\MedicationId;
use Shared\Domain\ValueObject\ScheduleId;
use Shared\Domain\ValueObject\UserId;

final class SendDoseReminderHandlerTest extends TestCase
{
    public function testSendsReminderAndMarksDoseAsNotified(): void
    {
        $doseId = new DoseEventId('00000000-0000-0000-0000-000000000001');
        $accountId = new UserId('00000000-0000-0000-0000-000000000002');
        $dose = DoseEvent::create(
            $doseId,
            new MedicationId('00000000-0000-0000-0000-000000000003'),
            new ScheduleId('00000000-0000-0000-0000-000000000004'),
            new \DateTimeImmutable('+10 minutes')
        );

        $doseRepo = $this->createMock(DoseEventRepository::class);
        $doseRepo->method('findById')->with($doseId)->willReturn($dose);
        $doseRepo->expects(self::once())->method('save')->with(self::callback(function (DoseEvent $d) {
            return $d->reminderSentAt() !== null;
        }));

        $device = DeviceToken::create($accountId, 'test-fcm-token', 'android', 'es');
        $deviceRepo = $this->createMock(DeviceTokenRepository::class);
        $deviceRepo->method('findByAccountId')->with($accountId)->willReturn([$device]);

        $gateway = $this->createMock(PushNotificationGateway::class);
        $gateway->expects(self::once())->method('send')->with(
            'test-fcm-token',
            'Hora de tu medicación',
            'Es hora de tomar Ibuprofeno (400 mg)',
            self::callback(function (array $data) {
                return ($data['type'] ?? '') === 'dose_reminder'
                    && ($data['medicationName'] ?? '') === 'Ibuprofeno'
                    && ($data['doseDisplay'] ?? '') === '400 mg'
                    && ($data['doseAmount'] ?? '') === '400'
                    && ($data['doseUnit'] ?? '') === 'mg'
                    && ($data['inAppBannersEnabled'] ?? '') === '1';
            })
        );

        $handler = new SendDoseReminderHandler($doseRepo, $deviceRepo, $gateway);
        $result = $handler(new SendDoseReminderCommand(
            $doseId->value(),
            $accountId->value(),
            'Ibuprofeno',
            Dose::of(400, 'mg'),
            $dose->scheduledAt(),
            10,
            true,
            true
        ));

        self::assertTrue($result->isSuccess());
        /** @var array<string, mixed> $data */
        $data = $result->getValue();
        self::assertSame(1, $data['sent']);
        self::assertSame(0, $data['failed']);
    }

    public function testSkipsIfDoseAlreadyNotifiedOrNotPending(): void
    {
        $doseId = new DoseEventId('00000000-0000-0000-0000-000000000001');
        $dose = DoseEvent::create(
            $doseId,
            new MedicationId('00000000-0000-0000-0000-000000000003'),
            new ScheduleId('00000000-0000-0000-0000-000000000004'),
            new \DateTimeImmutable('+10 minutes'),
            status: 'taken'
        );

        $doseRepo = $this->createMock(DoseEventRepository::class);
        $doseRepo->method('findById')->with($doseId)->willReturn($dose);
        $doseRepo->expects(self::never())->method('save');

        $deviceRepo = $this->createMock(DeviceTokenRepository::class);
        $gateway = $this->createMock(PushNotificationGateway::class);
        $gateway->expects(self::never())->method('send');

        $handler = new SendDoseReminderHandler($doseRepo, $deviceRepo, $gateway);
        $result = $handler(new SendDoseReminderCommand(
            $doseId->value(),
            '00000000-0000-0000-0000-000000000002',
            'Ibuprofeno',
            Dose::of(400, 'mg'),
            $dose->scheduledAt(),
            10,
            true,
            true
        ));

        self::assertTrue($result->isSuccess());
        /** @var array<string, mixed> $data */
        $data = $result->getValue();
        self::assertSame(0, $data['sent']);
        self::assertTrue($data['skipped'] ?? false);
    }
}
