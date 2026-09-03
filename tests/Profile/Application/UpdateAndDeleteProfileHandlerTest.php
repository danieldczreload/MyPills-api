<?php

declare(strict_types=1);

namespace App\Tests\Profile\Application;

use PHPUnit\Framework\TestCase;
use Profile\Application\Command\DeleteProfileCommand;
use Profile\Application\Command\DeleteProfileHandler;
use Profile\Application\Command\UpdateProfileCommand;
use Profile\Application\Command\UpdateProfileHandler;
use Profile\Domain\PatientProfile;
use Profile\Domain\ProfileRepository;
use Profile\Domain\ProfileTimezoneChangedEvent;
use Shared\Application\Bus\EventBus;
use Shared\Domain\ValueObject\ProfileId;
use Shared\Domain\ValueObject\UserId;

final class UpdateAndDeleteProfileHandlerTest extends TestCase
{
    public function testUpdateProfileValidationAndSuccess(): void
    {
        $repo = $this->createMock(ProfileRepository::class);
        $handler = new UpdateProfileHandler($repo, $this->createMock(EventBus::class));

        // Not found
        $repo->method('findById')->willReturn(null);
        $res = $handler(new UpdateProfileCommand('prof-1', 'acc-1', 'Name', new \DateTimeImmutable(), 'male', null));
        self::assertTrue($res->isFailure());

        // Forbidden
        $profile = new PatientProfile(new ProfileId('prof-1'), new UserId('acc-other'), 'Old Name', new \DateTimeImmutable('1990-01-01'), 'male', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $repoForb = $this->createMock(ProfileRepository::class);
        $repoForb->method('findById')->willReturn($profile);
        $handlerForb = new UpdateProfileHandler($repoForb, $this->createMock(EventBus::class));
        $res = $handlerForb(new UpdateProfileCommand('prof-1', 'acc-1', 'Name', new \DateTimeImmutable(), 'male', null));
        self::assertTrue($res->isFailure());

        // Empty Name Validation
        $profileVal = new PatientProfile(new ProfileId('prof-1'), new UserId('acc-1'), 'Old Name', new \DateTimeImmutable('1990-01-01'), 'male', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $repoVal = $this->createMock(ProfileRepository::class);
        $repoVal->method('findById')->willReturn($profileVal);
        $handlerVal = new UpdateProfileHandler($repoVal, $this->createMock(EventBus::class));
        $res = $handlerVal(new UpdateProfileCommand('prof-1', 'acc-1', '   ', new \DateTimeImmutable(), 'male', null));
        self::assertTrue($res->isFailure());

        // Success
        $profileSucc = new PatientProfile(new ProfileId('prof-1'), new UserId('acc-1'), 'Old Name', new \DateTimeImmutable('1990-01-01'), 'male', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $repoSucc = $this->createMock(ProfileRepository::class);
        $repoSucc->method('findById')->willReturn($profileSucc);
        $repoSucc->expects(self::once())->method('save')->with($profileSucc);
        $eventBusSucc = $this->createMock(EventBus::class);
        $eventBusSucc->expects(self::once())->method('publish')->with(self::callback(
            static function (object $event): bool {
                return $event instanceof ProfileTimezoneChangedEvent
                    && $event->profileId === 'prof-1'
                    && $event->previousTimezone === 'UTC'
                    && $event->timezone === 'America/El_Salvador';
            }
        ));
        $handlerSucc = new UpdateProfileHandler($repoSucc, $eventBusSucc);
        $birthDate = new \DateTimeImmutable('1990-01-01');
        $res = $handlerSucc(new UpdateProfileCommand('prof-1', 'acc-1', 'New Name', $birthDate, 'female', 'https://example.com/p.jpg', 'America/El_Salvador'));
        self::assertTrue($res->isSuccess());
        /** @var array<string, mixed> $updatedProfile */
        $updatedProfile = $res->getValue();
        self::assertSame('New Name', $updatedProfile['name']);
        self::assertSame('America/El_Salvador', $updatedProfile['timezone']);

        // Invalid timezone abbreviation
        $profileTz = new PatientProfile(new ProfileId('prof-1'), new UserId('acc-1'), 'Old Name', new \DateTimeImmutable('1990-01-01'), 'male', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $repoTz = $this->createMock(ProfileRepository::class);
        $repoTz->method('findById')->willReturn($profileTz);
        $eventBusTz = $this->createMock(EventBus::class);
        $eventBusTz->expects(self::never())->method('publish');
        $handlerTz = new UpdateProfileHandler($repoTz, $eventBusTz);
        $res = $handlerTz(new UpdateProfileCommand('prof-1', 'acc-1', 'New Name', $birthDate, 'female', null, 'CST'));
        self::assertTrue($res->isFailure());
        self::assertSame('Timezone "CST" is not a valid IANA identifier.', $res->getFailure()->getMessage());

        $profileKeep = new PatientProfile(new ProfileId('prof-1'), new UserId('acc-1'), 'Old Name', new \DateTimeImmutable('1990-01-01'), 'male', null, new \DateTimeImmutable(), new \DateTimeImmutable(), 'America/El_Salvador');
        $repoKeep = $this->createMock(ProfileRepository::class);
        $repoKeep->method('findById')->willReturn($profileKeep);
        $eventBusKeep = $this->createMock(EventBus::class);
        $eventBusKeep->expects(self::never())->method('publish');
        $handlerKeep = new UpdateProfileHandler($repoKeep, $eventBusKeep);
        $res = $handlerKeep(new UpdateProfileCommand('prof-1', 'acc-1', 'Same TZ', $birthDate, 'female', null, null));
        self::assertTrue($res->isSuccess());
        /** @var array<string, mixed> $kept */
        $kept = $res->getValue();
        self::assertSame('America/El_Salvador', $kept['timezone']);
    }

    public function testDeleteProfile(): void
    {
        $repo = $this->createMock(ProfileRepository::class);
        $handler = new DeleteProfileHandler($repo);

        $repo->method('findById')->willReturn(null);
        $res = $handler(new DeleteProfileCommand('prof-1', 'acc-1'));
        self::assertTrue($res->isFailure());

        $profile = new PatientProfile(new ProfileId('prof-1'), new UserId('acc-1'), 'Name', new \DateTimeImmutable('1990-01-01'), 'male', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $repoSucc = $this->createMock(ProfileRepository::class);
        $repoSucc->method('findById')->willReturn($profile);
        $repoSucc->expects(self::once())->method('delete')->with($profile);
        $handlerSucc = new DeleteProfileHandler($repoSucc);

        $res = $handlerSucc(new DeleteProfileCommand('prof-1', 'acc-1'));
        self::assertTrue($res->isSuccess());
    }
}
