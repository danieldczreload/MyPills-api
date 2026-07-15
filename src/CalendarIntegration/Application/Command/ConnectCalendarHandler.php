<?php

declare(strict_types=1);

namespace CalendarIntegration\Application\Command;

use CalendarIntegration\Domain\CalendarLink;
use CalendarIntegration\Domain\CalendarLinkRepository;
use Profile\Domain\ProfileRepository;
use Shared\Domain\Result;
use Shared\Domain\Failure;
use Shared\Domain\ValueObject\ProfileId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ConnectCalendarHandler
{
    public function __construct(
        private readonly CalendarLinkRepository $calendarLinkRepository,
        private readonly ProfileRepository $profileRepository
    ) {
    }

    /**
     * @return Result<array<string, mixed>>
     */
    public function __invoke(ConnectCalendarCommand $command): Result
    {
        $profileId = new ProfileId($command->profileId);
        $profile = $this->profileRepository->findById($profileId);

        if ($profile === null) {
            return Result::failure(Failure::notFound('Profile not found.'));
        }

        if ($profile->accountId()->value() !== $command->accountId) {
            return Result::failure(Failure::forbidden('You do not own this profile.'));
        }

        if (!in_array($command->provider, ['google', 'microsoft'], true)) {
            return Result::failure(Failure::validation('Invalid provider.'));
        }

        if (trim($command->refreshToken) === '') {
            return Result::failure(Failure::validation('RefreshToken cannot be empty.'));
        }

        $link = $this->calendarLinkRepository->findByProfileAndProvider($profileId, $command->provider);

        if ($link === null) {
            $link = CalendarLink::create($profileId, $command->provider, $command->refreshToken);
        } else {
            $link->updateRefreshToken($command->refreshToken);
        }

        $this->calendarLinkRepository->save($link);

        return Result::success([
            'id' => $link->id(),
            'profileId' => $link->profileId()->value(),
            'provider' => $link->provider(),
            'createdAt' => $link->createdAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $link->updatedAt()->format(\DateTimeInterface::ATOM),
        ]);
    }
}
