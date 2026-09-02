<?php

declare(strict_types=1);

namespace Notification\Application\Command;

use CalendarIntegration\Application\CalendarEventRemover;
use CalendarIntegration\Domain\CalendarEventMappingRepository;
use DoseEvent\Domain\DoseEvent;
use DoseEvent\Domain\DoseEventRepository;
use Medication\Domain\Medication;
use Medication\Domain\MedicationRepository;
use Notification\Domain\DeviceTokenRepository;
use Notification\Domain\PushNotificationGateway;
use Profile\Domain\ProfileRepository;
use Schedule\Domain\Schedule;
use Schedule\Domain\ScheduleRepository;
use Shared\Domain\Failure;
use Shared\Domain\Result;
use Shared\Domain\ValueObject\MedicationId;
use Shared\Domain\ValueObject\ProfileId;
use Shared\Domain\ValueObject\ScheduleId;
use Shared\Domain\ValueObject\UserId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CancelRecurringNotificationsHandler
{
    public function __construct(
        private readonly ProfileRepository $profileRepository,
        private readonly MedicationRepository $medicationRepository,
        private readonly ScheduleRepository $scheduleRepository,
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
    public function __invoke(CancelRecurringNotificationsCommand $command): Result
    {
        $profileId = new ProfileId($command->profileId);
        $profile = $this->profileRepository->findById($profileId);

        if ($profile === null) {
            return Result::failure(Failure::notFound('Profile not found.'));
        }

        if ($profile->accountId()->value() !== $command->accountId) {
            return Result::failure(Failure::forbidden('You do not own this profile.'));
        }

        /** @var Schedule[] $schedules */
        $schedules = [];

        if ($command->scheduleId !== null && $command->scheduleId !== '') {
            $scheduleId = new ScheduleId($command->scheduleId);
            $schedule = $this->scheduleRepository->findById($scheduleId);

            if ($schedule === null) {
                return Result::failure(Failure::notFound('Schedule not found.'));
            }

            $medication = $this->medicationRepository->findById($schedule->medicationId());
            if ($medication === null || !$medication->profileId()->equals($profileId)) {
                return Result::failure(Failure::forbidden('Schedule does not belong to this profile.'));
            }

            $schedules = [$schedule];
        } elseif ($command->medicationId !== null && $command->medicationId !== '') {
            $medId = new MedicationId($command->medicationId);
            $medication = $this->medicationRepository->findById($medId);

            if ($medication === null || !$medication->profileId()->equals($profileId)) {
                return Result::failure(Failure::notFound('Medication not found in this profile.'));
            }

            $schedules = $this->scheduleRepository->findByMedicationIds([$medId]);
        } else {
            $medications = $this->medicationRepository->findByProfileId($profileId);
            $medicationIds = array_map(static fn (Medication $m): MedicationId => $m->id(), $medications);
            $schedules = $this->scheduleRepository->findByMedicationIds($medicationIds);
        }

        $scheduleIds = array_map(static fn (Schedule $s): ScheduleId => $s->id(), $schedules);
        $pendingDoseEvents = $scheduleIds !== [] ? $this->doseEventRepository->findPendingByScheduleIds($scheduleIds) : [];
        $pendingDoseEventIds = array_map(static fn (DoseEvent $d): string => $d->id()->value(), $pendingDoseEvents);

        $calendarEventsDeleted = 0;
        if ($command->cancelCalendar && $pendingDoseEventIds !== []) {
            $mappings = $this->mappingRepository->findByDoseEventIds($pendingDoseEventIds);
            $calendarEventsDeleted = $this->calendarEventRemover->remove($profileId, $mappings);
        }

        $pushCancelled = false;
        if ($command->cancelPush) {
            if ($scheduleIds !== []) {
                $this->doseEventRepository->deletePendingByScheduleIds($scheduleIds);
            }

            $devices = $this->deviceTokenRepository->findByAccountId(new UserId($command->accountId));
            $payload = [
                'type' => 'cancel_recurring',
                'profileId' => $command->profileId,
                'scheduleId' => $command->scheduleId ?? '',
                'medicationId' => $command->medicationId ?? '',
                'cancelledDosesCount' => (string) count($pendingDoseEvents),
            ];

            $title = 'Recordatorios recurrentes cancelados';
            $body = 'Se han cancelado los recordatorios recurrentes programados.';

            foreach ($devices as $device) {
                try {
                    $this->pushNotificationGateway->send(
                        $device->token(),
                        $title,
                        $body,
                        $payload
                    );
                } catch (\Throwable) {
                    // Silently continue on individual device error
                }
            }

            $pushCancelled = true;
        }

        if ($command->deleteSchedule && $command->scheduleId !== null && $schedules !== []) {
            $this->scheduleRepository->delete($schedules[0]);
        }

        return Result::success([
            'profileId' => $command->profileId,
            'scheduleId' => $command->scheduleId,
            'medicationId' => $command->medicationId,
            'schedulesTargeted' => count($schedules),
            'pendingDosesCancelled' => count($pendingDoseEvents),
            'calendarEventsDeleted' => $calendarEventsDeleted,
            'pushCancelled' => $pushCancelled,
        ]);
    }
}
