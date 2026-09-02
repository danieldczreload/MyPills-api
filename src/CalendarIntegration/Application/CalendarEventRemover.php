<?php

declare(strict_types=1);

namespace CalendarIntegration\Application;

use CalendarIntegration\Domain\CalendarEventMapping;
use CalendarIntegration\Domain\CalendarEventMappingRepository;
use CalendarIntegration\Domain\CalendarLinkRepository;
use CalendarIntegration\Domain\CalendarLinkStatus;
use Shared\Domain\TokenVault;
use Shared\Domain\ValueObject\ProfileId;

final class CalendarEventRemover
{
    public function __construct(
        private readonly CalendarLinkRepository $calendarLinkRepository,
        private readonly CalendarEventMappingRepository $mappingRepository,
        private readonly CalendarProviderResolver $providerResolver,
        private readonly TokenVault $tokenVault
    ) {
    }

    /**
     * Best-effort delete used by cancel and schedule-deleted flows.
     * A mapping is dropped only when the provider confirms the delete (including 404).
     *
     * @param CalendarEventMapping[] $mappings
     */
    public function remove(ProfileId $profileId, array $mappings): int
    {
        if ($mappings === []) {
            return 0;
        }

        $deleted = 0;

        foreach ($this->groupByProvider($mappings) as $provider => $providerMappings) {
            $deleted += $this->deleteProviderEvents(
                $profileId,
                $provider,
                $providerMappings,
                skipReauthRequired: true
            )->deleted;
        }

        $this->mappingRepository->flush();

        return $deleted;
    }

    /**
     * Strict delete for one provider (disconnect). Does not skip reauth-required links.
     *
     * @param CalendarEventMapping[] $mappings
     */
    public function removeForProvider(ProfileId $profileId, string $provider, array $mappings): CalendarEventRemovalResult
    {
        if ($mappings === []) {
            return new CalendarEventRemovalResult(0, 0, false);
        }

        $result = $this->deleteProviderEvents(
            $profileId,
            $provider,
            $mappings,
            skipReauthRequired: false
        );
        $this->mappingRepository->flush();

        return $result;
    }

    /**
     * @param CalendarEventMapping[] $mappings
     *
     * @return array<string, list<CalendarEventMapping>>
     */
    private function groupByProvider(array $mappings): array
    {
        /** @var array<string, list<CalendarEventMapping>> $grouped */
        $grouped = [];
        foreach ($mappings as $mapping) {
            $grouped[$mapping->provider()][] = $mapping;
        }

        return $grouped;
    }

    /**
     * @param CalendarEventMapping[] $mappings
     */
    private function deleteProviderEvents(
        ProfileId $profileId,
        string $provider,
        array $mappings,
        bool $skipReauthRequired
    ): CalendarEventRemovalResult {
        $link = $this->calendarLinkRepository->findByProfileAndProvider($profileId, $provider);
        if ($link === null) {
            return new CalendarEventRemovalResult(0, count($mappings), false);
        }

        if ($skipReauthRequired && $link->status() === CalendarLinkStatus::REAUTH_REQUIRED) {
            return new CalendarEventRemovalResult(0, count($mappings), false);
        }

        try {
            $gateway = $this->providerResolver->resolveString($provider);
            $accessToken = $gateway->refreshAccessToken(
                $this->tokenVault->decrypt($link->encryptedRefreshToken())
            )->accessToken();
        } catch (\Throwable) {
            return new CalendarEventRemovalResult(0, count($mappings), true);
        }

        $deleted = 0;
        $failed = 0;
        foreach ($mappings as $mapping) {
            try {
                $gateway->deleteEvent($accessToken, $mapping->externalEventId());
                $this->mappingRepository->delete($mapping);
                ++$deleted;
            } catch (\Throwable) {
                // Keep the mapping so a later cancel/disconnect can retry the remote delete.
                ++$failed;
            }
        }

        return new CalendarEventRemovalResult($deleted, $failed, false);
    }
}
