<?php

declare(strict_types=1);

namespace App\Tests\Taxonomy\Application;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Profile\Domain\PatientProfile;
use Profile\Domain\ProfileRepository;
use Shared\Domain\ValueObject\ProfileId;
use Shared\Domain\ValueObject\UserId;
use Taxonomy\Application\Command\CreateTaxonomyGroupCommand;
use Taxonomy\Application\Command\CreateTaxonomyGroupHandler;
use Taxonomy\Domain\TaxonomyGroupRepository;

final class CreateTaxonomyGroupHandlerTest extends TestCase
{
    private TaxonomyGroupRepository&MockObject $groupRepo;
    private ProfileRepository&MockObject $profileRepo;
    private CreateTaxonomyGroupHandler $handler;

    protected function setUp(): void
    {
        $this->groupRepo = $this->createMock(TaxonomyGroupRepository::class);
        $this->profileRepo = $this->createMock(ProfileRepository::class);
        $this->handler = new CreateTaxonomyGroupHandler($this->groupRepo, $this->profileRepo);
    }

    public function testProfileNotFoundReturnsFailure(): void
    {
        $this->profileRepo->method('findById')->willReturn(null);
        $command = new CreateTaxonomyGroupCommand('prof-1', 'acc-1', 'category', 'Test');

        $result = ($this->handler)($command);
        self::assertTrue($result->isFailure());
        self::assertSame('Profile not found.', $result->getFailure()->getMessage());
    }

    public function testProfileForbiddenReturnsFailure(): void
    {
        $profile = new PatientProfile(new ProfileId('prof-1'), new UserId('acc-other'), 'John Doe', new \DateTimeImmutable('1990-01-01'), 'male', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $this->profileRepo->method('findById')->willReturn($profile);
        $command = new CreateTaxonomyGroupCommand('prof-1', 'acc-1', 'category', 'Test');

        $result = ($this->handler)($command);
        self::assertTrue($result->isFailure());
        self::assertSame('You do not own this profile.', $result->getFailure()->getMessage());
    }

    public function testEmptyNameReturnsValidationFailure(): void
    {
        $profile = new PatientProfile(new ProfileId('prof-1'), new UserId('acc-1'), 'John Doe', new \DateTimeImmutable('1990-01-01'), 'male', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $this->profileRepo->method('findById')->willReturn($profile);
        $command = new CreateTaxonomyGroupCommand('prof-1', 'acc-1', 'category', '   ');

        $result = ($this->handler)($command);
        self::assertTrue($result->isFailure());
        self::assertSame('Taxonomy group name cannot be empty.', $result->getFailure()->getMessage());
    }

    public function testSuccessfulCreationSavesAndReturnsPayload(): void
    {
        $profile = new PatientProfile(new ProfileId('prof-1'), new UserId('acc-1'), 'John Doe', new \DateTimeImmutable('1990-01-01'), 'male', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $this->profileRepo->method('findById')->willReturn($profile);
        $this->groupRepo->expects(self::once())->method('save');

        $command = new CreateTaxonomyGroupCommand('prof-1', 'acc-1', 'category', 'Cardio', 'Heart drugs', 'heart', 12345, true, 'client-1');

        $result = ($this->handler)($command);
        self::assertTrue($result->isSuccess());
        $data = $result->getValue();
        self::assertSame('prof-1', $data['profileId']);
        self::assertSame('category', $data['type']);
        self::assertSame('Cardio', $data['name']);
        self::assertSame('Heart drugs', $data['description']);
        self::assertSame('client-1', $data['clientId']);
    }
}
