<?php

declare(strict_types=1);

namespace Notification\Application\Command;

use Notification\Domain\DeviceTokenRepository;
use Notification\Domain\InvalidDeviceToken;
use Notification\Domain\PushNotificationGateway;
use Shared\Domain\Failure;
use Shared\Domain\Result;
use Shared\Domain\ValueObject\UserId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SendPushNotificationHandler
{
    public function __construct(
        private readonly DeviceTokenRepository $deviceTokenRepository,
        private readonly PushNotificationGateway $pushNotificationGateway
    ) {
    }

    /**
     * @return Result<array{sent: int, failed: int}>
     */
    public function __invoke(SendPushNotificationCommand $command): Result
    {
        if (trim($command->title) === '' || trim($command->body) === '') {
            return Result::failure(Failure::validation('Push notification title and body are required.'));
        }

        if (strlen($command->title) > 200 || strlen($command->body) > 2000) {
            return Result::failure(Failure::validation('Push notification title or body is too long.'));
        }

        if (count($command->data) > 32) {
            return Result::failure(Failure::validation('Push notification data cannot contain more than 32 entries.'));
        }

        foreach ($command->data as $key => $value) {
            if (!is_scalar($value) || strlen($key) > 128 || strlen((string) $value) > 2048) {
                return Result::failure(Failure::validation('Push notification data keys and values are invalid.'));
            }
        }

        $devices = $this->deviceTokenRepository->findByAccountId(new UserId($command->accountId));
        $sent = 0;
        $failed = 0;

        foreach ($devices as $device) {
            try {
                $this->pushNotificationGateway->send(
                    $device->token(),
                    $command->title,
                    $command->body,
                    $command->data
                );
                ++$sent;
            } catch (InvalidDeviceToken) {
                $this->deviceTokenRepository->delete($device);
                ++$failed;
            } catch (\Throwable) {
                ++$failed;
            }
        }

        if ($failed > 0) {
            return Result::failure(Failure::custom(
                'PUSH_PARTIAL_FAILURE',
                'Push notification delivery failed for one or more devices.',
                ['sent' => $sent, 'failed' => $failed]
            ));
        }

        return Result::success(['sent' => $sent, 'failed' => 0]);
    }
}
