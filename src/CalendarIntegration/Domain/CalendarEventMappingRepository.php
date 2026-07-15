<?php

declare(strict_types=1);

namespace CalendarIntegration\Domain;

interface CalendarEventMappingRepository
{
    public function save(CalendarEventMapping $mapping): void;

    public function findByDoseEventAndProvider(string $doseEventId, string $provider): ?CalendarEventMapping;

    public function delete(CalendarEventMapping $mapping): void;
}
