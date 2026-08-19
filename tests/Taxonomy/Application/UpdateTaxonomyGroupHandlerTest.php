<?php

declare(strict_types=1);

namespace App\Tests\Taxonomy\Application;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Profile\Domain\PatientProfile;
use Profile\Domain\ProfileRepository;
use Shared\Domain\ValueObject\ProfileId;
use Shared\Domain\ValueObject\TaxonomyGroupId;
use Shared\Domain\ValueObject\UserId;
use Taxonomy\Application\Command\UpdateTaxonomyGroupCommand;
use Taxonomy\Application\Command\UpdateTaxonomyGroupHandler;
use Taxonomy\Domain\TaxonomyGroup;
use Taxonomy\Domain\TaxonomyGroupRepository;

final class UpdateTaxonomyGroupHandlerTest extends TestCase
{
    private TaxonomyGroupRepository&MockObject $groupRepo;
    private ProfileRepository&MockObject $profileRepo;
    private UpdateTaxonomyGroupHandler $handler;

    protected function setUp(): void
    {
        $this->groupRepo = $this->createMock(TaxonomyGroupRepository::class);
        $this->profileRepo = $this->createMock(ProfileRepository::class);
        $this->handler = new UpdateTaxonomyGroupHandler($this->groupRepo, $this->profileRepo);
    }

    public function testGroupNotFoundReturnsFailure(): void
    {
        $profile = new PatientProfile(new ProfileId('prof-1'), new UserId('acc-1'), 'John Doe', new \DateTimeImmutable('1990-01-01'), 'male', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $this->profileRepo->method('findById')->willReturn($profile);
        $this->groupRepo->method('findById')->willReturn(null);

        $command = new UpdateTaxonomyGroupCommand('group-1', 'prof-1', 'acc-1', 'tag', 'Updated');
        $result = ($this->handler)($command);

        self::assertTrue($result->isFailure());
        self::assertSame('Taxonomy group not found.', $result->getFailure()->getMessage());
    }

    public function testSuccessfulUpdateSaves(): void
    {
        $profile = new PatientProfile(new ProfileId('prof-1'), new UserId('acc-1'), 'John Doe', new \DateTimeImmutable('1990-01-01'), 'male', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $this->profileRepo->method('findById')->willReturn($profile);

        $group = TaxonomyGroup::create(new TaxonomyGroupId('group-1'), new ProfileId('prof-1'), 'category', 'Old');
        $this->groupRepo->method('findById')->willReturn($group);
        $this->groupRepo->expects(self::once())->method('save');

        $command = new UpdateTaxonomyGroupCommand('group-1', 'prof-1', 'acc-1', 'tag', 'New Name');
        $result = ($this->handler)($command);

        self::assertTrue($result->isSuccess());
        self::assertSame('New Name', $result->getValue()['name']);
        self::assertSame('tag', $result->getValue()['type']);
    }
}
