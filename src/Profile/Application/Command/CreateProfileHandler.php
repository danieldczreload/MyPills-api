<?php

declare(strict_types=1);

namespace Profile\Application\Command;

use Profile\Domain\PatientProfile;
use Profile\Domain\ProfileRepository;
use Shared\Domain\Result;
use Shared\Domain\Failure;
use Shared\Domain\ValueObject\ProfileId;
use Shared\Domain\ValueObject\UserId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CreateProfileHandler
{
    public function __construct(
        private readonly ProfileRepository $profileRepository
    ) {
    }

    /**
     * @return Result<array{id: string, name: string, birthDate: string, gender: string, photoUrl: ?string, createdAt: string, updatedAt: string}>
     */
    public function __invoke(CreateProfileCommand $command): Result
    {
        if (trim($command->name) === '') {
            return Result::failure(Failure::validation('Profile name cannot be empty.'));
        }

        $profile = PatientProfile::create(
            ProfileId::generate(),
            new UserId($command->accountId),
            $command->name,
            $command->birthDate,
            $command->gender,
            $command->photoUrl
        );

        $this->profileRepository->save($profile);

        return Result::success([
            'id' => $profile->id()->value(),
            'name' => $profile->name(),
            'birthDate' => $profile->birthDate()->format(\DateTimeInterface::ATOM),
            'gender' => $profile->gender(),
            'photoUrl' => $profile->photoUrl(),
            'createdAt' => $profile->createdAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $profile->updatedAt()->format(\DateTimeInterface::ATOM),
        ]);
    }
}
