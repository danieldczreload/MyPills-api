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
use Taxonomy\Application\Query\GetTaxonomyGroupsHandler;
use Taxonomy\Application\Query\GetTaxonomyGroupsQuery;
use Taxonomy\Domain\TaxonomyGroup;
use Taxonomy\Domain\TaxonomyGroupRepository;

final class GetTaxonomyGroupsHandlerTest extends TestCase
{
    private TaxonomyGroupRepository&MockObject $groupRepo;
    private ProfileRepository&MockObject $profileRepo;
    private GetTaxonomyGroupsHandler $handler;

    protected function setUp(): void
    {
        $this->groupRepo = $this->createMock(TaxonomyGroupRepository::class);
        $this->profileRepo = $this->createMock(ProfileRepository::class);
        $this->handler = new GetTaxonomyGroupsHandler($this->groupRepo, $this->profileRepo);
    }

    public function testGetTaxonomyGroupsReturnsMappedList(): void
    {
        $profile = new PatientProfile(new ProfileId('prof-1'), new UserId('acc-1'), 'John Doe', new \DateTimeImmutable('1990-01-01'), 'male', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $this->profileRepo->method('findById')->willReturn($profile);

        $group = TaxonomyGroup::create(new TaxonomyGroupId('group-1'), new ProfileId('prof-1'), 'category', 'Test', 'Desc', 'icon', 12345, true, 'client-1');
        $this->groupRepo->method('findByProfileId')->willReturn([$group]);

        $query = new GetTaxonomyGroupsQuery('prof-1', 'acc-1');
        $result = ($this->handler)($query);

        self::assertTrue($result->isSuccess());
        self::assertCount(1, $result->getValue());
        self::assertSame('group-1', $result->getValue()[0]['id']);
        self::assertSame('Test', $result->getValue()[0]['name']);
    }
}
