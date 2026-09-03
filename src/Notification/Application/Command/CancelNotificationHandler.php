<?php

declare(strict_types=1);

namespace Notification\Application\Command;

use CalendarIntegration\Application\CalendarEventRemover;
use CalendarIntegration\Domain\CalendarEventMappingRepository;
use DoseEvent\Domain\DoseEventRepository;
use Medication\Domain\MedicationRepository;
use Notification\Domain\DeviceTokenRepository;
use Notification\Domain\PushNotificationGateway;
use Profile\Domain\ProfileRepository;
use Shared\Domain\Failure;
use Shared\Domain\Result;
use Shared\Domain\ValueObject\DoseEventId;
use Shared\Domain\ValueObject\ProfileId;
use Shared\Domain\ValueObject\UserId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CancelNotificationHandler
{
    public function __construct(
        private readonly ProfileRepository $profileRepository,
        private readonly MedicationRepository $medicationRepository,
        private readonly DoseEventRepository $doseEventRepository,
        private readonly DeviceTokenRepository $deviceTokenRepository,
        private readonly PushNotificationGateway $pushNotificationGateway,
        private readonly CalendarEventMappingRepository $mappingRepository,
        private readonly CalendarEventRemover $calendarEventRemover
    ) {
    }

    /**
     * @return Result<array<string, mixed>>
     */
    public function __invoke(CancelNotificationCommand $command): Result
    {
        $profileId = new ProfileId($command->profileId);
        $profile = $this->profileRepository->findById($profileId);

        if ($profile === null) {
            return Result::failure(Failure::notFound('Profile not found.'));
        }

        if ($profile->accountId()->value() !== $command->accountId) {
            return Result::failure(Failure::forbidden('You do not own this profile.'));
        }

        $doseEventId = new DoseEventId($command->doseEventId);
        $doseEvent = $this->doseEventRepository->findById($doseEventId);

        if ($doseEvent === null) {
            return Result::failure(Failure::notFound('Dose event not found.'));
        }

        $medication = $this->medicationRepository->findById($doseEvent->medicationId());
        if ($medication === null || !$medication->profileId()->equals($profileId)) {
            return Result::failure(Failure::forbidden('Dose event does not belong to this profile.'));
        }

        $pushCancelled = false;
        if ($command->cancelPush) {
            if ($doseEvent->status() === 'pending') {
                $doseEvent->markAs('skipped');
                $this->doseEventRepository->save($doseEvent);
            }

            $devices = $this->deviceTokenRepository->findByAccountId(new UserId($command->accountId));
            $payload = [
                'type' => 'cancel_notification',
                'doseEventId' => $command->doseEventId,
                'scheduleId' => $doseEvent->scheduleId()->value(),
                'profileId' => $command->profileId,
                'medicationId' => $medication->id()->value(),
            ];

            $title = 'Recordatorio cancelado';
            $body = sprintf('El recordatorio de %s ha sido cancelado.', $medication->name());

            foreach ($devices as $device) {
                try {
                    $this->pushNotificationGateway->send(
                        $device->token(),
                        $title,
                        $body,
                        $payload
                    );
                } catch (\Throwable) {
                    // Silently continue if an individual device token fails
                }
            }

            $pushCancelled = true;
        }

        $calendarEventsDeleted = 0;
        if ($command->cancelCalendar) {
            $mappings = $this->mappingRepository->findByDoseEventId($command->doseEventId);
            $calendarEventsDeleted = $this->calendarEventRemover->remove($profileId, $mappings);
        }

        return Result::success([
            'doseEventId' => $command->doseEventId,
            'status' => $doseEvent->status(),
            'pushCancelled' => $pushCancelled,
            'calendarEventsDeleted' => $calendarEventsDeleted,
        ]);
    }
}
