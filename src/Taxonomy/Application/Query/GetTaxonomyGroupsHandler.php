<?php

declare(strict_types=1);

namespace Taxonomy\Application\Query;

use Profile\Domain\ProfileRepository;
use Shared\Domain\Failure;
use Shared\Domain\Result;
use Shared\Domain\ValueObject\ProfileId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Taxonomy\Domain\TaxonomyGroupRepository;

#[AsMessageHandler]
final class GetTaxonomyGroupsHandler
{
    public function __construct(
        private readonly TaxonomyGroupRepository $taxonomyGroupRepository,
        private readonly ProfileRepository $profileRepository
    ) {
    }

    /**
     * @return Result<array<array{id: string, profileId: string, type: string, name: string, description: ?string, iconName: ?string, colorValue: ?int, isCustom: bool, clientId: ?string, createdAt: string, updatedAt: string}>>
     */
    public function __invoke(GetTaxonomyGroupsQuery $query): Result
    {
        $profileId = new ProfileId($query->profileId);
        $profile = $this->profileRepository->findById($profileId);

        if ($profile === null) {
            return Result::failure(Failure::notFound('Profile not found.'));
        }

        if ($profile->accountId()->value() !== $query->accountId) {
            return Result::failure(Failure::forbidden('You do not own this profile.'));
        }

        $groups = $this->taxonomyGroupRepository->findByProfileId($profileId);

        $data = array_map(static function ($group) {
            return [
                'id' => $group->id()->value(),
                'profileId' => $group->profileId()->value(),
                'type' => $group->type(),
                'name' => $group->name(),
                'description' => $group->description(),
                'iconName' => $group->iconName(),
                'colorValue' => $group->colorValue(),
                'isCustom' => $group->isCustom(),
                'clientId' => $group->clientId(),
                'createdAt' => $group->createdAt()->format(\DateTimeInterface::ATOM),
                'updatedAt' => $group->updatedAt()->format(\DateTimeInterface::ATOM),
            ];
        }, $groups);

        return Result::success($data);
    }
}
