<?php

declare(strict_types=1);

namespace CalendarIntegration\Application\Event;

use CalendarIntegration\Application\Command\SyncCalendarCommand;
use CalendarIntegration\Domain\CalendarLinkRepository;
use DoseEvent\Domain\DoseEventsExpandedEvent;
use Profile\Domain\ProfileRepository;
use Shared\Domain\ValueObject\ProfileId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class DoseEventsExpandedHandler
{
    public function __construct(
        private readonly ProfileRepository $profileRepository,
        private readonly CalendarLinkRepository $calendarLinkRepository,
        private readonly MessageBusInterface $commandBus
    ) {
    }

    public function __invoke(DoseEventsExpandedEvent $event): void
    {
        $profileId = new ProfileId($event->profileId);
        $profile = $this->profileRepository->findById($profileId);
        if ($profile === null) {
            return;
        }

        $links = $this->calendarLinkRepository->findByProfile($profileId);
        if ($links === []) {
            return;
        }

        $this->commandBus->dispatch(new SyncCalendarCommand(
            $profile->accountId()->value(),
            $profile->id()->value()
        ));
    }
}
