<?php

declare(strict_types=1);

namespace Notification\Application\Command;

use DoseEvent\Domain\DoseEventRepository;
use Notification\Domain\DeviceTokenRepository;
use Notification\Domain\InvalidDeviceToken;
use Notification\Domain\PushNotificationGateway;
use Shared\Domain\Result;
use Shared\Domain\ValueObject\Dose;
use Shared\Domain\ValueObject\DoseEventId;
use Shared\Domain\ValueObject\UserId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SendDoseReminderHandler
{
    public function __construct(
        private readonly DoseEventRepository $doseEventRepository,
        private readonly DeviceTokenRepository $deviceTokenRepository,
        private readonly PushNotificationGateway $pushNotificationGateway
    ) {
    }

    /**
     * @return Result<array<string, mixed>>
     */
    public function __invoke(SendDoseReminderCommand $command): Result
    {
        $doseEventId = new DoseEventId($command->doseEventId);
        $doseEvent = $this->doseEventRepository->findById($doseEventId);

        // Guard: Idempotency & state check
        if ($doseEvent === null || $doseEvent->status() !== 'pending' || $doseEvent->reminderSentAt() !== null) {
            return Result::success(['sent' => 0, 'skipped' => true]);
        }

        // If user disabled both push and in-app, mark sent without broadcasting
        if (!$command->doseRemindersEnabled && !$command->inAppBannersEnabled) {
            $doseEvent->markReminderSent(new \DateTimeImmutable());
            $this->doseEventRepository->save($doseEvent);
            return Result::success(['sent' => 0, 'disabled' => true]);
        }

        $devices = $this->deviceTokenRepository->findByAccountId(new UserId($command->accountId));
        $sent = 0;
        $failed = 0;

        $title = 'Hora de tu medicación';
        $doseDisplay = $command->dose?->display();
        $body = sprintf(
            'Es hora de tomar %s%s',
            $command->medicationName,
            $doseDisplay !== null ? ' (' . $doseDisplay . ')' : ''
        );

        $payload = [
            'type' => 'dose_reminder',
            'doseEventId' => $command->doseEventId,
            'medicationName' => $command->medicationName,
            ...($command->dose?->toPushData() ?? Dose::emptyPushData()),
            'scheduledAt' => $command->scheduledAt->format(\DateTimeInterface::ATOM),
            'anticipationMinutes' => (string) $command->reminderMinutesBefore,
            'doseRemindersEnabled' => $command->doseRemindersEnabled ? '1' : '0',
            'inAppBannersEnabled' => $command->inAppBannersEnabled ? '1' : '0',
        ];

        foreach ($devices as $device) {
            try {
                $this->pushNotificationGateway->send(
                    $device->token(),
                    $title,
                    $body,
                    $payload
                );
                ++$sent;
            } catch (InvalidDeviceToken) {
                $this->deviceTokenRepository->delete($device);
                ++$failed;
            } catch (\Throwable) {
                ++$failed;
            }
        }

        $doseEvent->markReminderSent(new \DateTimeImmutable());
        $this->doseEventRepository->save($doseEvent);

        return Result::success(['sent' => $sent, 'failed' => $failed]);
    }
}
