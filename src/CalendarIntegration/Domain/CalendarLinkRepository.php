<?php

declare(strict_types=1);

namespace CalendarIntegration\Domain;

use Shared\Domain\ValueObject\ProfileId;

interface CalendarLinkRepository
{
    public function save(CalendarLink $link): void;

    public function findByProfileAndProvider(ProfileId $profileId, string $provider): ?CalendarLink;

    /**
     * @return CalendarLink[]
     */
    public function findByProfile(ProfileId $profileId): array;

    public function delete(CalendarLink $link): void;
}
