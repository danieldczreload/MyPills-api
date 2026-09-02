<?php

declare(strict_types=1);

namespace Profile\Application\Query;

use DoseEvent\Domain\DoseEventRepository;
use Medication\Application\MedicationView;
use Medication\Domain\MedicationRepository;
use Profile\Domain\ProfileRepository;
use Profile\Domain\TombstoneRepository;
use Schedule\Application\ScheduleView;
use Schedule\Domain\ScheduleRepository;
use Shared\Domain\Result;
use Shared\Domain\Failure;
use Shared\Domain\ValueObject\ProfileId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Taxonomy\Domain\TaxonomyGroupRepository;

#[AsMessageHandler]
final class SyncProfileHandler
{
    public function __construct(
        private readonly ProfileRepository $profileRepository,
        private readonly MedicationRepository $medicationRepository,
        private readonly ScheduleRepository $scheduleRepository,
        private readonly DoseEventRepository $doseEventRepository,
        private readonly TombstoneRepository $tombstoneRepository,
        private readonly TaxonomyGroupRepository $taxonomyGroupRepository
    ) {
    }

    /**
     * @return Result<array{
     *     medications: array<array<string, mixed>>,
     *     schedules: array<array<string, mixed>>,
     *     doseEvents: array<array<string, mixed>>,
     *     taxonomyGroups: array<array<string, mixed>>,
     *     tombstones: array<array{id: string, type: string, deletedAt: string}>
     * }>
     */
    public function __invoke(SyncProfileQuery $query): Result
    {
        $profileId = new ProfileId($query->profileId);
        $profile = $this->profileRepository->findById($profileId);

        if ($profile === null) {
            return Result::failure(Failure::notFound('Profile not found.'));
        }

        if ($profile->accountId()->value() !== $query->accountId) {
            return Result::failure(Failure::forbidden('You do not own this profile.'));
        }

        // 1. Fetch Medications
        $medications = $this->medicationRepository->findByProfileId($profileId);
        $filteredMedications = array_filter(
            $medications,
            fn ($med) => $med->updatedAt() >= $query->since
        );
        $formattedMedications = array_map(MedicationView::toArray(...), $filteredMedications);

        // 2. Fetch Schedules
        $medicationIds = array_map(static fn ($med) => $med->id(), $medications);
        $schedules = $this->scheduleRepository->findByMedicationIds($medicationIds);
        $filteredSchedules = array_filter(
            $schedules,
            fn ($sch) => $sch->updatedAt() >= $query->since
        );
        $formattedSchedules = array_map(ScheduleView::toArray(...), $filteredSchedules);

        // 3. Fetch DoseEvents
        $scheduleIds = array_map(static fn ($sch) => $sch->id(), $schedules);
        // Fetch last 30 days up to next 30 days for sync buffer
        $from = $query->since->modify('-30 days');
        $to = $query->since->modify('+30 days');
        $doseEvents = $this->doseEventRepository->findByScheduleIdsAndRange($scheduleIds, $from, $to);
        $filteredDoseEvents = array_filter(
            $doseEvents,
            fn ($event) => $event->updatedAt() >= $query->since
        );

        $scheduleById = [];
        foreach ($schedules as $schedule) {
            $scheduleById[$schedule->id()->value()] = $schedule;
        }

        $formattedDoseEvents = array_map(static function ($event) use ($scheduleById) {
            $schedule = $scheduleById[$event->scheduleId()->value()] ?? null;

            return [
                'id' => $event->id()->value(),
                'medicationId' => $event->medicationId()->value(),
                'scheduleId' => $event->scheduleId()->value(),
                'scheduledAt' => $event->scheduledAt()->format(\DateTimeInterface::ATOM),
                'status' => $event->status(),
                'takenAt' => $event->takenAt()?->format(\DateTimeInterface::ATOM),
                'dose' => $schedule?->dose()?->toApiArray(),
                'clientId' => $event->clientId(),
                'createdAt' => $event->createdAt()->format(\DateTimeInterface::ATOM),
                'updatedAt' => $event->updatedAt()->format(\DateTimeInterface::ATOM),
            ];
        }, $filteredDoseEvents);

        // 4. Fetch TaxonomyGroups
        $taxonomyGroups = $this->taxonomyGroupRepository->findByProfileId($profileId);
        $filteredTaxonomies = array_filter(
            $taxonomyGroups,
            fn ($tax) => $tax->updatedAt() >= $query->since
        );
        $formattedTaxonomies = array_map(static function ($tax) {
            return [
                'id' => $tax->id()->value(),
                'profileId' => $tax->profileId()->value(),
                'type' => $tax->type(),
                'name' => $tax->name(),
                'description' => $tax->description(),
                'iconName' => $tax->iconName(),
                'colorValue' => $tax->colorValue(),
                'isCustom' => $tax->isCustom(),
                'clientId' => $tax->clientId(),
                'createdAt' => $tax->createdAt()->format(\DateTimeInterface::ATOM),
                'updatedAt' => $tax->updatedAt()->format(\DateTimeInterface::ATOM),
            ];
        }, $filteredTaxonomies);

        // 5. Fetch Tombstones
        $tombstones = $this->tombstoneRepository->findByProfileIdSince($profileId, $query->since);
        $formattedTombstones = array_map(static function ($t) {
            return [
                'id' => $t->entityId(),
                'type' => $t->entityType(),
                'deletedAt' => $t->deletedAt()->format(\DateTimeInterface::ATOM),
            ];
        }, $tombstones);

        return Result::success([
            'medications' => array_values($formattedMedications),
            'schedules' => array_values($formattedSchedules),
            'doseEvents' => array_values($formattedDoseEvents),
            'taxonomyGroups' => array_values($formattedTaxonomies),
            'tombstones' => array_values($formattedTombstones),
        ]);
    }
}
