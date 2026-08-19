<?php

declare(strict_types=1);

namespace Profile\Application\Command;

use Profile\Domain\ProfileRepository;
use Shared\Domain\Result;
use Shared\Domain\Failure;
use Shared\Domain\ValueObject\ProfileId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class UpdateProfileHandler
{
    public function __construct(
        private readonly ProfileRepository $profileRepository
    ) {
    }

    /**
     * @return Result<array{id: string, name: string, birthDate: string, gender: string, photoUrl: ?string, timezone: string, createdAt: string, updatedAt: string}>
     */
    public function __invoke(UpdateProfileCommand $command): Result
    {
        $profileId = new ProfileId($command->id);
        $profile = $this->profileRepository->findById($profileId);

        if ($profile === null) {
            return Result::failure(Failure::notFound('Profile not found.'));
        }

        if ($profile->accountId()->value() !== $command->accountId) {
            return Result::failure(Failure::forbidden('You do not own this profile.'));
        }

        if (trim($command->name) === '') {
            return Result::failure(Failure::validation('Profile name cannot be empty.'));
        }

        $profile->update(
            $command->name,
            $command->birthDate,
            $command->gender,
            $command->photoUrl,
            $command->timezone
        );
        $this->profileRepository->save($profile);

        return Result::success([
            'id' => $profile->id()->value(),
            'name' => $profile->name(),
            'birthDate' => $profile->birthDate()->format(\DateTimeInterface::ATOM),
            'gender' => $profile->gender(),
            'photoUrl' => $profile->photoUrl(),
            'timezone' => $profile->timezone(),
            'createdAt' => $profile->createdAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $profile->updatedAt()->format(\DateTimeInterface::ATOM),
        ]);
    }
}
