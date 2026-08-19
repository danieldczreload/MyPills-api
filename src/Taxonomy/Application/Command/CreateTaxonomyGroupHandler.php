<?php

declare(strict_types=1);

namespace Taxonomy\Application\Command;

use Profile\Domain\ProfileRepository;
use Shared\Domain\Failure;
use Shared\Domain\Result;
use Shared\Domain\ValueObject\ProfileId;
use Shared\Domain\ValueObject\TaxonomyGroupId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Taxonomy\Domain\TaxonomyGroup;
use Taxonomy\Domain\TaxonomyGroupRepository;

#[AsMessageHandler]
final class CreateTaxonomyGroupHandler
{
    public function __construct(
        private readonly TaxonomyGroupRepository $taxonomyGroupRepository,
        private readonly ProfileRepository $profileRepository
    ) {
    }

    /**
     * @return Result<array{id: string, profileId: string, type: string, name: string, description: ?string, iconName: ?string, colorValue: ?int, isCustom: bool, clientId: ?string, createdAt: string, updatedAt: string}>
     */
    public function __invoke(CreateTaxonomyGroupCommand $command): Result
    {
        $profileId = new ProfileId($command->profileId);
        $profile = $this->profileRepository->findById($profileId);

        if ($profile === null) {
            return Result::failure(Failure::notFound('Profile not found.'));
        }

        if ($profile->accountId()->value() !== $command->accountId) {
            return Result::failure(Failure::forbidden('You do not own this profile.'));
        }

        if (trim($command->name) === '') {
            return Result::failure(Failure::validation('Taxonomy group name cannot be empty.'));
        }

        // Idempotency check via clientId
        if ($command->clientId !== null && $command->clientId !== '') {
            $existing = $this->taxonomyGroupRepository->findByClientId($command->clientId);
            if ($existing !== null) {
                return Result::success([
                    'id' => $existing->id()->value(),
                    'profileId' => $existing->profileId()->value(),
                    'type' => $existing->type(),
                    'name' => $existing->name(),
                    'description' => $existing->description(),
                    'iconName' => $existing->iconName(),
                    'colorValue' => $existing->colorValue(),
                    'isCustom' => $existing->isCustom(),
                    'clientId' => $existing->clientId(),
                    'createdAt' => $existing->createdAt()->format(\DateTimeInterface::ATOM),
                    'updatedAt' => $existing->updatedAt()->format(\DateTimeInterface::ATOM),
                ]);
            }
        }

        $group = TaxonomyGroup::create(
            TaxonomyGroupId::generate(),
            $profileId,
            $command->type,
            $command->name,
            $command->description,
            $command->iconName,
            $command->colorValue,
            $command->isCustom,
            $command->clientId
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
