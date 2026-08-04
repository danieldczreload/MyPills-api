<?php

declare(strict_types=1);

namespace CalendarIntegration\Domain;

interface CalendarAuthorizationRequestRepository
{
    public function save(CalendarAuthorizationRequest $request): void;

    public function findByStateHash(string $stateHash): ?CalendarAuthorizationRequest;

    public function consume(CalendarAuthorizationRequest $request, \DateTimeImmutable $now): bool;

    public function deleteExpired(\DateTimeImmutable $now): void;
}
