<?php

declare(strict_types=1);

namespace App\Tests\Notification\Application;

use Notification\Application\Command\DeregisterDeviceCommand;
use Notification\Application\Command\DeregisterDeviceHandler;
use Notification\Domain\DeviceToken;
use Notification\Domain\DeviceTokenRepository;
use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\UserId;

final class DeregisterDeviceHandlerTest extends TestCase
{
    public function testDeregisterDeviceSuccessAndFailures(): void
    {
        $repo = $this->createMock(DeviceTokenRepository::class);
        $handler = new DeregisterDeviceHandler($repo);

        // Not found
        $repo->method('findById')->with('dev-1')->willReturn(null);
        $res = $handler(new DeregisterDeviceCommand('dev-1', 'acc-1'));
        self::assertTrue($res->isFailure());

        // Forbidden
        $token = new DeviceToken('dev-1', new UserId('acc-other'), 'token', 'android', 'es-MX', new \DateTimeImmutable());
        $repo = $this->createMock(DeviceTokenRepository::class);
        $repo->method('findById')->with('dev-1')->willReturn($token);
        $handler = new DeregisterDeviceHandler($repo);
        $res = $handler(new DeregisterDeviceCommand('dev-1', 'acc-1'));
        self::assertTrue($res->isFailure());

        // Success
        $token = new DeviceToken('dev-1', new UserId('acc-1'), 'token', 'android', 'es-MX', new \DateTimeImmutable());
        $repo = $this->createMock(DeviceTokenRepository::class);
        $repo->method('findById')->with('dev-1')->willReturn($token);
        $repo->expects(self::once())->method('delete')->with($token);
        $handler = new DeregisterDeviceHandler($repo);
        $res = $handler(new DeregisterDeviceCommand('dev-1', 'acc-1'));
        self::assertTrue($res->isSuccess());
    }
}
