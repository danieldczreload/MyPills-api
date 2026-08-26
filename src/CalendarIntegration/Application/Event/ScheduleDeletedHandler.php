<?php

declare(strict_types=1);

namespace CalendarIntegration\Application\Event;

use CalendarIntegration\Application\CalendarProviderResolver;
use CalendarIntegration\Domain\CalendarEventMapping;
use CalendarIntegration\Domain\CalendarEventMappingRepository;
use CalendarIntegration\Domain\CalendarLinkRepository;
use CalendarIntegration\Domain\CalendarLinkStatus;
use DoseEvent\Domain\DoseEvent;
use DoseEvent\Domain\DoseEventRepository;
use Schedule\Domain\ScheduleDeletedEvent;
use Shared\Domain\TokenVault;
use Shared\Domain\ValueObject\ProfileId;
use Shared\Domain\ValueObject\ScheduleId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ScheduleDeletedHandler
{
    public function __construct(
        private readonly CalendarLinkRepository $calendarLinkRepository,
        private readonly CalendarEventMappingRepository $mappingRepository,
        private readonly DoseEventRepository $doseEventRepository,
        private readonly CalendarProviderResolver $providerResolver,
        private readonly TokenVault $tokenVault
    ) {
    }

    public function __invoke(ScheduleDeletedEvent $event): void
    {
        $profileId = new ProfileId($event->profileId);
        $scheduleId = new ScheduleId($event->scheduleId);

        $doseEvents = $this->doseEventRepository->findByScheduleId($scheduleId);
        $doseEventIds = array_map(static fn (DoseEvent $d): string => $d->id()->value(), $doseEvents);

        if ($doseEventIds === []) {
            return;
        }

        $mappings = $this->mappingRepository->findByDoseEventIds($doseEventIds);
        if ($mappings === []) {
            return;
        }

        /** @var array<string, CalendarEventMapping[]> $mappingsByProvider */
        $mappingsByProvider = [];
        foreach ($mappings as $mapping) {
            $mappingsByProvider[$mapping->provider()][] = $mapping;
        }

        foreach ($mappingsByProvider as $provider => $provMappings) {
            $link = $this->calendarLinkRepository->findByProfileAndProvider($profileId, $provider);
            if ($link === null || $link->status() === CalendarLinkStatus::REAUTH_REQUIRED) {
                continue;
            }

            try {
                $gateway = $this->providerResolver->resolveString($provider);
                $tokens = $gateway->refreshAccessToken($this->tokenVault->decrypt($link->encryptedRefreshToken()));

                foreach ($provMappings as $mapping) {
                    try {
                        $gateway->deleteEvent($tokens->accessToken(), $mapping->externalEventId());
                    } catch (\Throwable) {
                        // Silently continue on external API failure
                    }
                    $this->mappingRepository->delete($mapping);
                }
            } catch (\Throwable) {
                // Silently continue on provider refresh failure
            }
        }

        $this->mappingRepository->flush();
    }
}
