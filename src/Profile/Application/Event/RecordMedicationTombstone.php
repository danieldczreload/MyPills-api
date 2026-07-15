<?php

declare(strict_types=1);

namespace Profile\Application\Event;

use Medication\Domain\MedicationDeletedEvent;
use Profile\Domain\Tombstone;
use Profile\Domain\TombstoneRepository;
use Shared\Domain\ValueObject\ProfileId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class RecordMedicationTombstone
{
    public function __construct(
        private readonly TombstoneRepository $tombstoneRepository
    ) {
    }

    public function __invoke(MedicationDeletedEvent $event): void
    {
        $tombstone = Tombstone::create(
            new ProfileId($event->profileId),
            'medication',
            $event->medicationId
        );

        $this->tombstoneRepository->save($tombstone);
    }
}
