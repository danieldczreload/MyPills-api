<?php

declare(strict_types=1);

namespace App\Tests\Notification\Application;

use Notification\Application\Command\SendPushNotificationCommand;
use Notification\Application\Command\SendPushNotificationHandler;
use Notification\Domain\DeviceToken;
use Notification\Domain\DeviceTokenRepository;
use Notification\Domain\InvalidDeviceToken;
use Notification\Domain\PushNotificationGateway;
use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\UserId;

final class SendPushNotificationHandlerTest extends TestCase
{
    public function testReturnsValidationFailureForOversizedPayload(): void
    {
        $handler = new SendPushNotificationHandler(
            $this->createMock(DeviceTokenRepository::class),
            $this->createMock(PushNotificationGateway::class)
        );

        $result = $handler(new SendPushNotificationCommand(
            '00000000-0000-0000-0000-000000000001',
            str_repeat('x', 201),
            'Delivery check'
        ));

        self::assertTrue($result->isFailure());
        self::assertSame('VALIDATION', $result->getFailure()->getType());
    }

    public function testReturnsFailureWhenADeviceCannotBeReached(): void
    {
        $device = DeviceToken::create(
            new UserId('00000000-0000-0000-0000-000000000001'),
            'fcm-token',
            'android',
            'en-US'
        );
        $repository = $this->createMock(DeviceTokenRepository::class);
        $repository->method('findByAccountId')->willReturn([$device]);

        $gateway = $this->createMock(PushNotificationGateway::class);
        $gateway->expects(self::once())
            ->method('send')
            ->willThrowException(new InvalidDeviceToken('invalid token'));
        $repository->expects(self::once())->method('delete')->with($device);

        $handler = new SendPushNotificationHandler($repository, $gateway);
        $result = $handler(new SendPushNotificationCommand(
            '00000000-0000-0000-0000-000000000001',
            'Test notification',
            'Delivery check'
        ));

        self::assertTrue($result->isFailure());
        self::assertSame('PUSH_PARTIAL_FAILURE', $result->getFailure()->getType());
        self::assertSame(['sent' => 0, 'failed' => 1], $result->getFailure()->getDetails());
    }
}
