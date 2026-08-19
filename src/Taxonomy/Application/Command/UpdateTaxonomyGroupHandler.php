<?php

declare(strict_types=1);

namespace Taxonomy\Application\Command;

use Profile\Domain\ProfileRepository;
use Shared\Domain\Failure;
use Shared\Domain\Result;
use Shared\Domain\ValueObject\ProfileId;
use Shared\Domain\ValueObject\TaxonomyGroupId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Taxonomy\Domain\TaxonomyGroupRepository;

#[AsMessageHandler]
final class UpdateTaxonomyGroupHandler
{
    public function __construct(
        private readonly TaxonomyGroupRepository $taxonomyGroupRepository,
        private readonly ProfileRepository $profileRepository
    ) {
    }

    /**
     * @return Result<array{id: string, profileId: string, type: string, name: string, description: ?string, iconName: ?string, colorValue: ?int, isCustom: bool, clientId: ?string, createdAt: string, updatedAt: string}>
     */
    public function __invoke(UpdateTaxonomyGroupCommand $command): Result
    {
        $profileId = new ProfileId($command->profileId);
        $profile = $this->profileRepository->findById($profileId);

        if ($profile === null) {
            return Result::failure(Failure::notFound('Profile not found.'));
        }

        if ($profile->accountId()->value() !== $command->accountId) {
            return Result::failure(Failure::forbidden('You do not own this profile.'));
        }

        $groupId = new TaxonomyGroupId($command->id);
        $group = $this->taxonomyGroupRepository->findById($groupId);

        if ($group === null) {
            return Result::failure(Failure::notFound('Taxonomy group not found.'));
        }

        if (!$group->profileId()->equals($profileId)) {
            return Result::failure(Failure::forbidden('Taxonomy group does not belong to this profile.'));
        }

        if (trim($command->name) === '') {
            return Result::failure(Failure::validation('Taxonomy group name cannot be empty.'));
        }

        $group->update(
            $command->type,
            $command->name,
            $command->description,
            $command->iconName,
            $command->colorValue,
            $command->isCustom
        );
        $this->taxonomyGroupRepository->save($group);

        return Result::success([
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
        ]);
    }
}
