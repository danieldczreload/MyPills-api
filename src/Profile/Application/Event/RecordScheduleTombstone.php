<?php

declare(strict_types=1);

namespace Profile\Application\Event;

use Schedule\Domain\ScheduleDeletedEvent;
use Profile\Domain\Tombstone;
use Profile\Domain\TombstoneRepository;
use Shared\Domain\ValueObject\ProfileId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class RecordScheduleTombstone
{
    public function __construct(
        private readonly TombstoneRepository $tombstoneRepository
    ) {
    }

    public function __invoke(ScheduleDeletedEvent $event): void
    {
        $tombstone = Tombstone::create(
            new ProfileId($event->profileId),
            'schedule',
            $event->scheduleId
        );

        $this->tombstoneRepository->save($tombstone);
    }
}
