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

final class NotificationHandlersExtraTest extends TestCase
{
    public function testSendPushNotificationValidationFailures(): void
    {
        $repo = $this->createMock(DeviceTokenRepository::class);
        $gw = $this->createMock(PushNotificationGateway::class);
        $handler = new SendPushNotificationHandler($repo, $gw);

        // Empty title
        $res = $handler(new SendPushNotificationCommand('acc-1', '', 'body'));
        self::assertTrue($res->isFailure());

        // Long title
        $res = $handler(new SendPushNotificationCommand('acc-1', str_repeat('a', 201), 'body'));
        self::assertTrue($res->isFailure());

        // Too many data items
        $data = [];
        for ($i = 0; $i < 35; $i++) {
            $data['key_' . $i] = 'val_' . $i;
        }
        $res = $handler(new SendPushNotificationCommand('acc-1', 'Title', 'body', $data));
        self::assertTrue($res->isFailure());
    }

    public function testSendPushNotificationPartialFailureAndInvalidToken(): void
    {
        $repo = $this->createMock(DeviceTokenRepository::class);
        $gw = $this->createMock(PushNotificationGateway::class);

        $userId = new UserId('acc-1');
        $device1 = new DeviceToken('dev-1', $userId, 'invalid-token-1', 'android', 'es-MX', new \DateTimeImmutable());
        $device2 = new DeviceToken('dev-2', $userId, 'failing-token-2', 'ios', 'en-US', new \DateTimeImmutable());

        $repo->method('findByAccountId')->with($userId)->willReturn([$device1, $device2]);

        $gw->expects(self::exactly(2))
            ->method('send')
            ->willReturnCallback(function (string $token) {
                if ($token === 'invalid-token-1') {
                    throw new InvalidDeviceToken('Token expired');
                }
                throw new \RuntimeException('Gateway timeout');
            });

        $repo->expects(self::once())->method('delete')->with($device1);

        $handler = new SendPushNotificationHandler($repo, $gw);
        $res = $handler(new SendPushNotificationCommand('acc-1', 'Take meds', 'Time to take aspirin'));

        self::assertTrue($res->isFailure());
        self::assertSame('PUSH_PARTIAL_FAILURE', $res->getFailure()->getType());
    }
}
