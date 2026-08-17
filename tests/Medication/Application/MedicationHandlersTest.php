<?php

declare(strict_types=1);

namespace App\Tests\Medication\Application;

use Medication\Application\Command\CreateMedicationCommand;
use Medication\Application\Command\CreateMedicationHandler;
use Medication\Application\Command\DeleteMedicationCommand;
use Medication\Application\Command\DeleteMedicationHandler;
use Medication\Application\Command\UpdateMedicationCommand;
use Medication\Application\Command\UpdateMedicationHandler;
use Medication\Application\Query\GetMedicationsHandler;
use Medication\Application\Query\GetMedicationsQuery;
use Medication\Domain\Medication;
use Medication\Domain\MedicationDeletedEvent;
use Medication\Domain\MedicationRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Profile\Domain\PatientProfile;
use Profile\Domain\ProfileRepository;
use Shared\Application\Bus\EventBus;
use Shared\Domain\ValueObject\MedicationId;
use Shared\Domain\ValueObject\ProfileId;
use Shared\Domain\ValueObject\UserId;

final class MedicationHandlersTest extends TestCase
{
    private MedicationRepository&MockObject $medRepo;
    private ProfileRepository&MockObject $profileRepo;
    private EventBus&MockObject $eventBus;

    protected function setUp(): void
    {
        $this->medRepo = $this->createMock(MedicationRepository::class);
        $this->profileRepo = $this->createMock(ProfileRepository::class);
        $this->eventBus = $this->createMock(EventBus::class);
    }

    public function testCreateMedicationValidationAndSuccess(): void
    {
        $handler = new CreateMedicationHandler($this->medRepo, $this->profileRepo);

        // Profile not found
        $this->profileRepo->method('findById')->willReturn(null);
        $res = $handler(new CreateMedicationCommand('prof-1', 'acc-1', 'Aspirin', '100mg'));
        self::assertTrue($res->isFailure());

        // Profile forbidden
        $profile = new PatientProfile(new ProfileId('prof-1'), new UserId('acc-other'), 'Name', new \DateTimeImmutable('1990-01-01'), 'male', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $this->profileRepo = $this->createMock(ProfileRepository::class);
        $this->profileRepo->method('findById')->willReturn($profile);
        $handler = new CreateMedicationHandler($this->medRepo, $this->profileRepo);
        $res = $handler(new CreateMedicationCommand('prof-1', 'acc-1', 'Aspirin', '100mg'));
        self::assertTrue($res->isFailure());

        // Empty name validation
        $profile = new PatientProfile(new ProfileId('prof-1'), new UserId('acc-1'), 'Name', new \DateTimeImmutable('1990-01-01'), 'male', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $this->profileRepo = $this->createMock(ProfileRepository::class);
        $this->profileRepo->method('findById')->willReturn($profile);
        $handler = new CreateMedicationHandler($this->medRepo, $this->profileRepo);
        $res = $handler(new CreateMedicationCommand('prof-1', 'acc-1', '  ', '100mg'));
        self::assertTrue($res->isFailure());

        // Empty dosage validation
        $res = $handler(new CreateMedicationCommand('prof-1', 'acc-1', 'Aspirin', '  '));
        self::assertTrue($res->isFailure());

        // Idempotent return existing
        $existing = new Medication(new MedicationId('med-1'), new ProfileId('prof-1'), 'Aspirin', '100mg', null, null, 'cid-1', new \DateTimeImmutable(), new \DateTimeImmutable());
        $this->medRepo->method('findByClientId')->with('cid-1')->willReturn($existing);
        $res = $handler(new CreateMedicationCommand('prof-1', 'acc-1', 'Aspirin', '100mg', null, null, 'cid-1'));
        self::assertTrue($res->isSuccess());
        /** @var array<string, mixed> $existingData */
        $existingData = $res->getValue();
        self::assertSame('med-1', $existingData['id']);

        // Success
        $this->medRepo = $this->createMock(MedicationRepository::class);
        $this->medRepo->expects(self::once())->method('save');
        $handler = new CreateMedicationHandler($this->medRepo, $this->profileRepo);
        $res = $handler(new CreateMedicationCommand('prof-1', 'acc-1', 'Ibuprofen', '400mg', 'With food', 'https://example.com/med.jpg'));
        self::assertTrue($res->isSuccess());
        /** @var array<string, mixed> $createdData */
        $createdData = $res->getValue();
        self::assertSame('Ibuprofen', $createdData['name']);
    }

    public function testUpdateMedicationValidationAndSuccess(): void
    {
        $profile = new PatientProfile(new ProfileId('prof-1'), new UserId('acc-1'), 'Name', new \DateTimeImmutable('1990-01-01'), 'male', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $this->profileRepo->method('findById')->willReturn($profile);

        $medication = new Medication(new MedicationId('med-1'), new ProfileId('prof-1'), 'Aspirin', '100mg', null, null, null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $this->medRepo->method('findById')->willReturn($medication);
        $this->medRepo->expects(self::once())->method('save');

        $handler = new UpdateMedicationHandler($this->medRepo, $this->profileRepo);
        $res = $handler(new UpdateMedicationCommand('med-1', 'prof-1', 'acc-1', 'Aspirin Cardio', '81mg', 'Daily at morning', 'https://pic.jpg'));

        self::assertTrue($res->isSuccess());
        /** @var array<string, mixed> $updatedData */
        $updatedData = $res->getValue();
        self::assertSame('Aspirin Cardio', $updatedData['name']);
        self::assertSame('81mg', $updatedData['dosage']);
    }

    public function testDeleteMedicationSuccess(): void
    {
        $profile = new PatientProfile(new ProfileId('prof-1'), new UserId('acc-1'), 'Name', new \DateTimeImmutable('1990-01-01'), 'male', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $this->profileRepo->method('findById')->willReturn($profile);

        $medication = new Medication(new MedicationId('med-1'), new ProfileId('prof-1'), 'Aspirin', '100mg', null, null, null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $this->medRepo->method('findById')->willReturn($medication);
        $this->medRepo->expects(self::once())->method('delete')->with($medication);
        $this->eventBus->expects(self::once())->method('publish')->with(self::isInstanceOf(MedicationDeletedEvent::class));

        $handler = new DeleteMedicationHandler($this->medRepo, $this->profileRepo, $this->eventBus);
        $res = $handler(new DeleteMedicationCommand('med-1', 'prof-1', 'acc-1'));

        self::assertTrue($res->isSuccess());
    }

    public function testGetMedicationsSuccess(): void
    {
        $profile = new PatientProfile(new ProfileId('prof-1'), new UserId('acc-1'), 'Name', new \DateTimeImmutable('1990-01-01'), 'male', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $this->profileRepo->method('findById')->willReturn($profile);

        $medication = new Medication(new MedicationId('med-1'), new ProfileId('prof-1'), 'Aspirin', '100mg', null, null, null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $this->medRepo->method('findByProfileId')->willReturn([$medication]);

        $handler = new GetMedicationsHandler($this->medRepo, $this->profileRepo);
        $res = $handler(new GetMedicationsQuery('prof-1', 'acc-1'));

        self::assertTrue($res->isSuccess());
        /** @var list<array<string, mixed>> $listData */
        $listData = $res->getValue();
        self::assertCount(1, $listData);
        self::assertSame('Aspirin', $listData[0]['name']);
    }
}
