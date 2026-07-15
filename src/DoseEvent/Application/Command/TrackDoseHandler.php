<?php

declare(strict_types=1);

namespace DoseEvent\Application\Event; // Wait, let's use Application\Command namespace

namespace DoseEvent\Application\Command;

use DoseEvent\Domain\DoseEvent;
use DoseEvent\Domain\DoseEventRepository;
use Medication\Domain\MedicationRepository;
use Profile\Domain\ProfileRepository;
use Schedule\Domain\ScheduleRepository;
use Shared\Domain\Result;
use Shared\Domain\Failure;
use Shared\Domain\ValueObject\DoseEventId;
use Shared\Domain\ValueObject\ProfileId;
use Shared\Domain\ValueObject\ScheduleId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class TrackDoseHandler
{
    public function __construct(
        private readonly DoseEventRepository $doseEventRepository,
        private readonly ScheduleRepository $scheduleRepository,
        private readonly MedicationRepository $medicationRepository,
        private readonly ProfileRepository $profileRepository
    ) {
    }

    /**
     * @return Result<array<string, mixed>>
     */
    public function __invoke(TrackDoseCommand $command): Result
    {
        $profileId = new ProfileId($command->profileId);
        $profile = $this->profileRepository->findById($profileId);

        if ($profile === null) {
            return Result::failure(Failure::notFound('Profile not found.'));
        }

        if ($profile->accountId()->value() !== $command->accountId) {
            return Result::failure(Failure::forbidden('You do not own this profile.'));
        }

        $scheduleId = new ScheduleId($command->scheduleId);
        $schedule = $this->scheduleRepository->findById($scheduleId);

        if ($schedule === null) {
            return Result::failure(Failure::notFound('Schedule not found.'));
        }

        $medication = $this->medicationRepository->findById($schedule->medicationId());
        if ($medication === null || !$medication->profileId()->equals($profileId)) {
            return Result::failure(Failure::forbidden('Schedule does not belong to this profile.'));
        }

        if (!in_array($command->status, ['pending', 'taken', 'missed', 'skipped'], true)) {
            return Result::failure(Failure::validation('Invalid status.'));
        }

        // Try to find by clientId if provided
        $doseEvent = null;
        if ($command->clientId !== null && $command->clientId !== '') {
            $doseEvent = $this->doseEventRepository->findByClientId($command->clientId);
        }

        // If not found, try to find by scheduleId and scheduledAt
        if ($doseEvent === null) {
            $existingEvents = $this->doseEventRepository->findByScheduleIdsAndRange(
                [$scheduleId],
                $command->scheduledAt,
                $command->scheduledAt
            );
            if (count($existingEvents) > 0) {
                $doseEvent = $existingEvents[0];
            }
        }

        $takenAt = $command->status === 'taken' ? ($command->takenAt ?? new \DateTimeImmutable()) : null;

        if ($doseEvent === null) {
            // Create a new DoseEvent
            $doseEvent = DoseEvent::create(
                DoseEventId::generate(),
                $schedule->medicationId(),
                $scheduleId,
                $command->scheduledAt,
                $command->status,
                $takenAt,
                $command->clientId
            );
        } else {
            // Update existing
            $doseEvent->markAs($command->status, $takenAt);
        }

        $this->doseEventRepository->save($doseEvent);

        return Result::success([
            'id' => $doseEvent->id()->value(),
            'medicationId' => $doseEvent->medicationId()->value(),
            'scheduleId' => $doseEvent->scheduleId()->value(),
            'scheduledAt' => $doseEvent->scheduledAt()->format(\DateTimeInterface::ATOM),
            'status' => $doseEvent->status(),
            'takenAt' => $doseEvent->takenAt()?->format(\DateTimeInterface::ATOM),
            'clientId' => $doseEvent->clientId(),
            'createdAt' => $doseEvent->createdAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $doseEvent->updatedAt()->format(\DateTimeInterface::ATOM),
        ]);
    }
}
