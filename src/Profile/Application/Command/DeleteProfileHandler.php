<?php

declare(strict_types=1);

namespace Profile\Application\Command;

use Profile\Domain\ProfileRepository;
use Shared\Domain\Result;
use Shared\Domain\Failure;
use Shared\Domain\ValueObject\ProfileId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class DeleteProfileHandler
{
    public function __construct(
        private readonly ProfileRepository $profileRepository
    ) {
    }

    /**
     * @return Result<null>
     */
    public function __invoke(DeleteProfileCommand $command): Result
    {
        $profileId = new ProfileId($command->id);
        $profile = $this->profileRepository->findById($profileId);

        if ($profile === null) {
            return Result::failure(Failure::notFound('Profile not found.'));
        }

        if ($profile->accountId()->value() !== $command->accountId) {
            return Result::failure(Failure::forbidden('You do not own this profile.'));
        }

        $this->profileRepository->delete($profile);

        return Result::success();
    }
}
