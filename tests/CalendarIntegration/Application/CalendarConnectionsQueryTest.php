<?php

declare(strict_types=1);

namespace App\Tests\CalendarIntegration\Application;

use CalendarIntegration\Application\Query\GetCalendarConnectionsHandler;
use CalendarIntegration\Application\Query\GetCalendarConnectionsQuery;
use CalendarIntegration\Domain\CalendarLink;
use CalendarIntegration\Domain\CalendarLinkRepository;
use PHPUnit\Framework\TestCase;
use Profile\Domain\PatientProfile;
use Profile\Domain\ProfileRepository;
use Shared\Domain\ValueObject\ProfileId;
use Shared\Domain\ValueObject\UserId;

final class CalendarConnectionsQueryTest extends TestCase
{
    public function testGetCalendarConnections(): void
    {
        $linkRepo = $this->createMock(CalendarLinkRepository::class);
        $profileRepo = $this->createMock(ProfileRepository::class);
        $handler = new GetCalendarConnectionsHandler($linkRepo, $profileRepo);

        // Not found
        $profileRepo->method('findById')->willReturn(null);
        $res = $handler(new GetCalendarConnectionsQuery('prof-1', 'acc-1'));
        self::assertTrue($res->isFailure());

        // Forbidden
        $profile = new PatientProfile(new ProfileId('prof-1'), new UserId('acc-other'), 'Name', new \DateTimeImmutable('1990-01-01'), 'male', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $profileRepo = $this->createMock(ProfileRepository::class);
        $profileRepo->method('findById')->willReturn($profile);
        $handler = new GetCalendarConnectionsHandler($linkRepo, $profileRepo);
        $res = $handler(new GetCalendarConnectionsQuery('prof-1', 'acc-1'));
        self::assertTrue($res->isFailure());

        // Success
        $profile = new PatientProfile(new ProfileId('prof-1'), new UserId('acc-1'), 'Name', new \DateTimeImmutable('1990-01-01'), 'male', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $profileRepo = $this->createMock(ProfileRepository::class);
        $profileRepo->method('findById')->willReturn($profile);
        $link = CalendarLink::create(new ProfileId('prof-1'), 'google', 'enc-token');
        $linkRepo->method('findByProfile')->willReturn([$link]);

        $handler = new GetCalendarConnectionsHandler($linkRepo, $profileRepo);
        $res = $handler(new GetCalendarConnectionsQuery('prof-1', 'acc-1'));
        self::assertTrue($res->isSuccess());
        /** @var list<array<string, mixed>> $connections */
        $connections = $res->getValue();
        self::assertCount(1, $connections);
        self::assertSame('google', $connections[0]['provider']);
    }
}
