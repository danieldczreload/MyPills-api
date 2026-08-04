<?php

declare(strict_types=1);

namespace CalendarIntegration\Application\Query;

use CalendarIntegration\Domain\CalendarLinkRepository;
use Profile\Domain\ProfileRepository;
use Shared\Domain\Failure;
use Shared\Domain\Result;
use Shared\Domain\ValueObject\ProfileId;
use Shared\Domain\ValueObject\UserId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GetCalendarConnectionsHandler
{
    public function __construct(
        private readonly CalendarLinkRepository $calendarLinkRepository,
        private readonly ProfileRepository $profileRepository
    ) {
    }

    /**
     * @return Result<array<int, array{provider: string, status: string, connected: bool, updatedAt: string}>>
     */
    public function __invoke(GetCalendarConnectionsQuery $query): Result
    {
        $profileId = new ProfileId($query->profileId);
        $accountId = new UserId($query->accountId);
        $profile = $this->profileRepository->findById($profileId);

        if ($profile === null) {
            return Result::failure(Failure::notFound('Profile not found.'));
        }

        if (!$profile->accountId()->equals($accountId)) {
            return Result::failure(Failure::forbidden('You do not own this profile.'));
        }

        /** @var array<int, array{provider: string, status: string, connected: bool, updatedAt: string}> $connections */
        $connections = array_map(
            static fn ($link): array => [
                'provider' => $link->provider(),
                'status' => $link->status()->value,
                'connected' => true,
                'updatedAt' => $link->updatedAt()->format(\DateTimeInterface::ATOM),
            ],
            $this->calendarLinkRepository->findByProfile($profileId)
        );

        return Result::success($connections);
    }
}
