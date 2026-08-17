<?php

declare(strict_types=1);

namespace App\Tests\Notification\Infrastructure;

use Notification\Infrastructure\LoggerPushNotificationGateway;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class LoggerPushNotificationGatewayTest extends TestCase
{
    public function testSendLogsNotification(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('info')
            ->with(
                'Push notification dispatched.',
                self::callback(static fn (array $context): bool => isset($context['tokenFingerprint']) && isset($context['dataKeys']))
            );

        $gw = new LoggerPushNotificationGateway($logger);
        $gw->send('some-fcm-device-token', 'Reminder', 'Time to take meds', ['doseId' => '123']);
    }
}
