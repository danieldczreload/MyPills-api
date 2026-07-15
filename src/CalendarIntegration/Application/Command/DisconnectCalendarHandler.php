<?php

declare(strict_types=1);

namespace CalendarIntegration\Application\Command;

use CalendarIntegration\Domain\CalendarLinkRepository;
use Profile\Domain\ProfileRepository;
use Shared\Domain\Result;
use Shared\Domain\Failure;
use Shared\Domain\ValueObject\ProfileId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class DisconnectCalendarHandler
{
    public function __construct(
        private readonly CalendarLinkRepository $calendarLinkRepository,
        private readonly ProfileRepository $profileRepository
    ) {
    }

    /**
     * @return Result<null>
     */
    public function __invoke(DisconnectCalendarCommand $command): Result
    {
        $profileId = new ProfileId($command->profileId);
        $profile = $this->profileRepository->findById($profileId);

        if ($profile === null) {
            return Result::failure(Failure::notFound('Profile not found.'));
        }

        if ($profile->accountId()->value() !== $command->accountId) {
            return Result::failure(Failure::forbidden('You do not own this profile.'));
        }

        $link = $this->calendarLinkRepository->findByProfileAndProvider($profileId, $command->provider);

        if ($link === null) {
            return Result::failure(Failure::notFound('Calendar link not found.'));
        }

        $this->calendarLinkRepository->delete($link);

        return Result::success();
    }
}
