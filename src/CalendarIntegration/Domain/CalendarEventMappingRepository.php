<?php

declare(strict_types=1);

namespace CalendarIntegration\Domain;

use Shared\Domain\ValueObject\ProfileId;

interface CalendarEventMappingRepository
{
    public function save(CalendarEventMapping $mapping): void;

    public function findByDoseEventAndProvider(string $doseEventId, string $provider): ?CalendarEventMapping;

    /**
     * @return CalendarEventMapping[]
     */
    public function findByProfileAndProvider(ProfileId $profileId, string $provider): array;

    /**
     * @param string[] $doseEventIds
     *
     * @return array<string, CalendarEventMapping> Keyed by "<doseEventId>:<provider>".
     */
    public function findByDoseEvents(array $doseEventIds, string $provider): array;

    public function flush(): void;

    public function delete(CalendarEventMapping $mapping): void;
}
