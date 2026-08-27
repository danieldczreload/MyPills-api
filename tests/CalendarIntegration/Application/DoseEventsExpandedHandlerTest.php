<?php

declare(strict_types=1);

namespace App\Tests\CalendarIntegration\Application;

use CalendarIntegration\Application\Command\SyncCalendarCommand;
use CalendarIntegration\Application\Event\DoseEventsExpandedHandler;
use CalendarIntegration\Domain\CalendarLink;
use CalendarIntegration\Domain\CalendarLinkRepository;
use DoseEvent\Domain\DoseEventsExpandedEvent;
use PHPUnit\Framework\TestCase;
use Profile\Domain\PatientProfile;
use Profile\Domain\ProfileRepository;
use Shared\Domain\ValueObject\ProfileId;
use Shared\Domain\ValueObject\UserId;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class DoseEventsExpandedHandlerTest extends TestCase
{
    public function testDoesNothingWhenProfileIsMissing(): void
    {
        $profileRepo = $this->createMock(ProfileRepository::class);
        $linkRepo = $this->createMock(CalendarLinkRepository::class);
        $commandBus = $this->createMock(MessageBusInterface::class);

        $profileRepo->method('findById')->willReturn(null);
        $commandBus->expects(self::never())->method('dispatch');

        $handler = new DoseEventsExpandedHandler($profileRepo, $linkRepo, $commandBus);
        $handler(new DoseEventsExpandedEvent('prof-1', 'sch-1'));
    }

    public function testDoesNothingWhenNoCalendarIsLinked(): void
    {
        $profileRepo = $this->createMock(ProfileRepository::class);
        $linkRepo = $this->createMock(CalendarLinkRepository::class);
        $commandBus = $this->createMock(MessageBusInterface::class);

        $profile = new PatientProfile(
            new ProfileId('prof-1'),
            new UserId('acc-1'),
            'Name',
            new \DateTimeImmutable('1990-01-01'),
            'male',
            null,
            new \DateTimeImmutable(),
            new \DateTimeImmutable()
        );
        $profileRepo->method('findById')->willReturn($profile);
        $linkRepo->method('findByProfile')->willReturn([]);
        $commandBus->expects(self::never())->method('dispatch');

        $handler = new DoseEventsExpandedHandler($profileRepo, $linkRepo, $commandBus);
        $handler(new DoseEventsExpandedEvent('prof-1', 'sch-1'));
    }

    public function testDispatchesCalendarSyncWhenALinkExists(): void
    {
        $profileRepo = $this->createMock(ProfileRepository::class);
        $linkRepo = $this->createMock(CalendarLinkRepository::class);
        $commandBus = $this->createMock(MessageBusInterface::class);

        $profileId = new ProfileId('prof-1');
        $profile = new PatientProfile(
            $profileId,
            new UserId('acc-1'),
            'Name',
            new \DateTimeImmutable('1990-01-01'),
            'male',
            null,
            new \DateTimeImmutable(),
            new \DateTimeImmutable()
        );
        $profileRepo->method('findById')->willReturn($profile);
        $linkRepo->method('findByProfile')->willReturn([
            CalendarLink::create($profileId, 'google', 'enc-refresh'),
        ]);
        $commandBus->expects(self::once())->method('dispatch')->with(self::callback(
            static function (object $command): bool {
                return $command instanceof SyncCalendarCommand
                    && $command->accountId === 'acc-1'
                    && $command->profileId === 'prof-1';
            }
        ))->willReturnCallback(static fn (object $command): Envelope => new Envelope($command));

        $handler = new DoseEventsExpandedHandler($profileRepo, $linkRepo, $commandBus);
        $handler(new DoseEventsExpandedEvent('prof-1', 'sch-1'));
    }
}
