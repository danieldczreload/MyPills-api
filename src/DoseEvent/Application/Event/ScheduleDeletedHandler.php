<?php

declare(strict_types=1);

namespace DoseEvent\Application\Event;

use DoseEvent\Domain\DoseEventRepository;
use Schedule\Domain\ScheduleDeletedEvent;
use Shared\Domain\ValueObject\ScheduleId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ScheduleDeletedHandler
{
    public function __construct(
        private readonly DoseEventRepository $doseEventRepository
    ) {
    }

    public function __invoke(ScheduleDeletedEvent $event): void
    {
        $scheduleId = new ScheduleId($event->scheduleId);
        $this->doseEventRepository->deletePendingByScheduleIds([$scheduleId]);
    }
}
