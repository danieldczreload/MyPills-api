<?php

declare(strict_types=1);

namespace Taxonomy\Application\Command;

use Profile\Domain\ProfileRepository;
use Profile\Domain\Tombstone;
use Profile\Domain\TombstoneRepository;
use Shared\Domain\Failure;
use Shared\Domain\Result;
use Shared\Domain\ValueObject\ProfileId;
use Shared\Domain\ValueObject\TaxonomyGroupId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Taxonomy\Domain\TaxonomyGroupRepository;

#[AsMessageHandler]
final class DeleteTaxonomyGroupHandler
{
    public function __construct(
        private readonly TaxonomyGroupRepository $taxonomyGroupRepository,
        private readonly ProfileRepository $profileRepository,
        private readonly TombstoneRepository $tombstoneRepository
    ) {
    }

    /**
     * @return Result<null>
     */
    public function __invoke(DeleteTaxonomyGroupCommand $command): Result
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

        $this->taxonomyGroupRepository->delete($group);

        // Record tombstone for sync
        $this->tombstoneRepository->save(Tombstone::create(
            $profileId,
            'taxonomy_group',
            $group->id()->value()
        ));

        return Result::success(null);
    }
}
