<?php

declare(strict_types=1);

namespace Profile\Application\Query;

use Profile\Domain\ProfileRepository;
use Shared\Domain\Result;
use Shared\Domain\ValueObject\UserId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GetProfilesHandler
{
    public function __construct(
        private readonly ProfileRepository $profileRepository
    ) {
    }

    /**
     * @return Result<array<array{id: string, name: string, birthDate: string, gender: string, photoUrl: ?string, createdAt: string, updatedAt: string}>>
     */
    public function __invoke(GetProfilesQuery $query): Result
    {
        $profiles = $this->profileRepository->findByAccountId(new UserId($query->accountId));

        $data = array_map(static function ($profile) {
            return [
                'id' => $profile->id()->value(),
                'name' => $profile->name(),
                'birthDate' => $profile->birthDate()->format(\DateTimeInterface::ATOM),
                'gender' => $profile->gender(),
                'photoUrl' => $profile->photoUrl(),
                'createdAt' => $profile->createdAt()->format(\DateTimeInterface::ATOM),
                'updatedAt' => $profile->updatedAt()->format(\DateTimeInterface::ATOM),
            ];
        }, $profiles);

        return Result::success($data);
    }
}
