<?php

declare(strict_types=1);

namespace App\Tests\Taxonomy\Application;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Profile\Domain\PatientProfile;
use Profile\Domain\ProfileRepository;
use Profile\Domain\TombstoneRepository;
use Shared\Domain\ValueObject\ProfileId;
use Shared\Domain\ValueObject\TaxonomyGroupId;
use Shared\Domain\ValueObject\UserId;
use Taxonomy\Application\Command\DeleteTaxonomyGroupCommand;
use Taxonomy\Application\Command\DeleteTaxonomyGroupHandler;
use Taxonomy\Domain\TaxonomyGroup;
use Taxonomy\Domain\TaxonomyGroupRepository;

final class DeleteTaxonomyGroupHandlerTest extends TestCase
{
    private TaxonomyGroupRepository&MockObject $groupRepo;
    private ProfileRepository&MockObject $profileRepo;
    private TombstoneRepository&MockObject $tombstoneRepo;
    private DeleteTaxonomyGroupHandler $handler;

    protected function setUp(): void
    {
        $this->groupRepo = $this->createMock(TaxonomyGroupRepository::class);
        $this->profileRepo = $this->createMock(ProfileRepository::class);
        $this->tombstoneRepo = $this->createMock(TombstoneRepository::class);
        $this->handler = new DeleteTaxonomyGroupHandler($this->groupRepo, $this->profileRepo, $this->tombstoneRepo);
    }

    public function testDeleteSavesTombstoneAndDeletes(): void
    {
        $profile = new PatientProfile(new ProfileId('prof-1'), new UserId('acc-1'), 'John Doe', new \DateTimeImmutable('1990-01-01'), 'male', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $this->profileRepo->method('findById')->willReturn($profile);

        $group = TaxonomyGroup::create(new TaxonomyGroupId('group-1'), new ProfileId('prof-1'), 'category', 'Test');
        $this->groupRepo->method('findById')->willReturn($group);
        $this->groupRepo->expects(self::once())->method('delete');
        $this->tombstoneRepo->expects(self::once())->method('save');

        $command = new DeleteTaxonomyGroupCommand('group-1', 'prof-1', 'acc-1');
        $result = ($this->handler)($command);

        self::assertTrue($result->isSuccess());
    }

    public function testProfileNotFoundReturnsFailure(): void
    {
        $this->profileRepo->method('findById')->willReturn(null);
        $command = new DeleteTaxonomyGroupCommand('group-1', 'prof-1', 'acc-1');
        $result = ($this->handler)($command);

        self::assertTrue($result->isFailure());
        self::assertSame('Profile not found.', $result->getFailure()->getMessage());
    }

    public function testProfileForbiddenReturnsFailure(): void
    {
        $profile = new PatientProfile(new ProfileId('prof-1'), new UserId('acc-other'), 'John Doe', new \DateTimeImmutable('1990-01-01'), 'male', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $this->profileRepo->method('findById')->willReturn($profile);
        $command = new DeleteTaxonomyGroupCommand('group-1', 'prof-1', 'acc-1');
        $result = ($this->handler)($command);

        self::assertTrue($result->isFailure());
        self::assertSame('You do not own this profile.', $result->getFailure()->getMessage());
    }

    public function testGroupNotFoundReturnsFailure(): void
    {
        $profile = new PatientProfile(new ProfileId('prof-1'), new UserId('acc-1'), 'John Doe', new \DateTimeImmutable('1990-01-01'), 'male', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $this->profileRepo->method('findById')->willReturn($profile);
        $this->groupRepo->method('findById')->willReturn(null);

        $command = new DeleteTaxonomyGroupCommand('group-1', 'prof-1', 'acc-1');
        $result = ($this->handler)($command);

        self::assertTrue($result->isFailure());
        self::assertSame('Taxonomy group not found.', $result->getFailure()->getMessage());
    }

    public function testGroupBelongsToDifferentProfileReturnsForbidden(): void
    {
        $profile = new PatientProfile(new ProfileId('prof-1'), new UserId('acc-1'), 'John Doe', new \DateTimeImmutable('1990-01-01'), 'male', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $this->profileRepo->method('findById')->willReturn($profile);

        $group = TaxonomyGroup::create(new TaxonomyGroupId('group-1'), new ProfileId('prof-other'), 'category', 'Test');
        $this->groupRepo->method('findById')->willReturn($group);

        $command = new DeleteTaxonomyGroupCommand('group-1', 'prof-1', 'acc-1');
        $result = ($this->handler)($command);

        self::assertTrue($result->isFailure());
        self::assertSame('Taxonomy group does not belong to this profile.', $result->getFailure()->getMessage());
    }
}
