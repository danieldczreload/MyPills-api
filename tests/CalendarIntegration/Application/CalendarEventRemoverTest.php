<?php

declare(strict_types=1);

namespace App\Tests\CalendarIntegration\Application;

use CalendarIntegration\Application\CalendarEventRemover;
use CalendarIntegration\Application\CalendarProviderResolver;
use CalendarIntegration\Domain\CalendarEventMapping;
use CalendarIntegration\Domain\CalendarEventMappingRepository;
use CalendarIntegration\Domain\CalendarLink;
use CalendarIntegration\Domain\CalendarLinkRepository;
use CalendarIntegration\Domain\CalendarOAuthTokens;
use CalendarIntegration\Domain\CalendarProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Domain\TokenVault;
use Shared\Domain\ValueObject\ProfileId;

final class CalendarEventRemoverTest extends TestCase
{
    public function testRemoveDeletesMappingWhenRemoteDeleteSucceeds(): void
    {
        $profileId = new ProfileId('prof-1');
        $mapping = CalendarEventMapping::create('dose-1', 'google', 'ext-1');
        [$remover, $mapRepo, $google] = $this->removerWithActiveLink($profileId);

        $google->expects(self::once())->method('deleteEvent')->with('access-token', 'ext-1');
        $mapRepo->expects(self::once())->method('delete')->with($mapping);
        $mapRepo->expects(self::once())->method('flush');

        self::assertSame(1, $remover->remove($profileId, [$mapping]));
    }

    public function testRemoveDeletesMappingWhenRemoteEventAlreadyGone(): void
    {
        $profileId = new ProfileId('prof-1');
        $mapping = CalendarEventMapping::create('dose-1', 'google', 'ext-gone');
        [$remover, $mapRepo, $google] = $this->removerWithActiveLink($profileId);

        // Gateways treat HTTP 404 as success (no throw).
        $google->expects(self::once())->method('deleteEvent')->with('access-token', 'ext-gone');
        $mapRepo->expects(self::once())->method('delete')->with($mapping);
        $mapRepo->expects(self::once())->method('flush');

        self::assertSame(1, $remover->remove($profileId, [$mapping]));
    }

    public function testRemoveKeepsMappingWhenRemoteDeleteFails(): void
    {
        $profileId = new ProfileId('prof-1');
        $mapping = CalendarEventMapping::create('dose-1', 'google', 'ext-1');
        [$remover, $mapRepo, $google] = $this->removerWithActiveLink($profileId);

        $google->method('deleteEvent')->willThrowException(new \RuntimeException('Google Calendar API delete failed with status 500.'));
        $mapRepo->expects(self::never())->method('delete');
        $mapRepo->expects(self::once())->method('flush');

        self::assertSame(0, $remover->remove($profileId, [$mapping]));
    }

    public function testRemoveKeepsSuccessfulMappingsAndRetriesFailedOnes(): void
    {
        $profileId = new ProfileId('prof-1');
        $ok = CalendarEventMapping::create('dose-1', 'google', 'ext-ok');
        $fail = CalendarEventMapping::create('dose-2', 'google', 'ext-fail');
        [$remover, $mapRepo, $google] = $this->removerWithActiveLink($profileId);

        $google->method('deleteEvent')->willReturnCallback(static function (string $_token, string $eventId): void {
            if ($eventId === 'ext-fail') {
                throw new \RuntimeException('Google Calendar API delete failed with status 503.');
            }
        });
        $mapRepo->expects(self::once())->method('delete')->with($ok);
        $mapRepo->expects(self::once())->method('flush');

        self::assertSame(1, $remover->remove($profileId, [$ok, $fail]));
    }

    public function testRemoveKeepsMappingWhenLinkMissingOrReauthRequired(): void
    {
        $profileId = new ProfileId('prof-1');
        $mapping = CalendarEventMapping::create('dose-1', 'google', 'ext-1');

        $linkRepo = $this->createMock(CalendarLinkRepository::class);
        $linkRepo->method('findByProfileAndProvider')->willReturn(null);
        $mapRepo = $this->createMock(CalendarEventMappingRepository::class);
        $mapRepo->expects(self::never())->method('delete');
        $mapRepo->expects(self::once())->method('flush');
        $google = $this->createMock(CalendarProvider::class);
        $google->expects(self::never())->method('deleteEvent');

        $remover = new CalendarEventRemover(
            $linkRepo,
            $mapRepo,
            new CalendarProviderResolver($google, $this->createMock(CalendarProvider::class)),
            $this->createMock(TokenVault::class)
        );

        self::assertSame(0, $remover->remove($profileId, [$mapping]));

        $link = CalendarLink::create($profileId, 'google', 'enc-refresh');
        $link->markReauthorizationRequired();
        $linkRepo = $this->createMock(CalendarLinkRepository::class);
        $linkRepo->method('findByProfileAndProvider')->willReturn($link);
        $mapRepo = $this->createMock(CalendarEventMappingRepository::class);
        $mapRepo->expects(self::never())->method('delete');
        $google = $this->createMock(CalendarProvider::class);
        $google->expects(self::never())->method('refreshAccessToken');

        $remover = new CalendarEventRemover(
            $linkRepo,
            $mapRepo,
            new CalendarProviderResolver($google, $this->createMock(CalendarProvider::class)),
            $this->createMock(TokenVault::class)
        );

        self::assertSame(0, $remover->remove($profileId, [$mapping]));
    }

    public function testRemoveKeepsMappingWhenTokenRefreshFails(): void
    {
        $profileId = new ProfileId('prof-1');
        $mapping = CalendarEventMapping::create('dose-1', 'google', 'ext-1');
        $link = CalendarLink::create($profileId, 'google', 'enc-refresh');

        $linkRepo = $this->createMock(CalendarLinkRepository::class);
        $linkRepo->method('findByProfileAndProvider')->willReturn($link);
        $mapRepo = $this->createMock(CalendarEventMappingRepository::class);
        $mapRepo->expects(self::never())->method('delete');

        $google = $this->createMock(CalendarProvider::class);
        $google->method('refreshAccessToken')->willThrowException(new \RuntimeException('Token invalid'));
        $vault = $this->createMock(TokenVault::class);
        $vault->method('decrypt')->willReturn('dec-refresh');

        $remover = new CalendarEventRemover(
            $linkRepo,
            $mapRepo,
            new CalendarProviderResolver($google, $this->createMock(CalendarProvider::class)),
            $vault
        );

        self::assertSame(0, $remover->remove($profileId, [$mapping]));
    }

    public function testRemoveReturnsZeroForEmptyMappings(): void
    {
        $mapRepo = $this->createMock(CalendarEventMappingRepository::class);
        $mapRepo->expects(self::never())->method('flush');

        $remover = new CalendarEventRemover(
            $this->createMock(CalendarLinkRepository::class),
            $mapRepo,
            new CalendarProviderResolver($this->createMock(CalendarProvider::class), $this->createMock(CalendarProvider::class)),
            $this->createMock(TokenVault::class)
        );

        self::assertSame(0, $remover->remove(new ProfileId('prof-1'), []));
    }

    public function testRemoveForProviderReportsRefreshFailure(): void
    {
        $profileId = new ProfileId('prof-1');
        $mapping = CalendarEventMapping::create('dose-1', 'google', 'ext-1');
        $link = CalendarLink::create($profileId, 'google', 'enc-refresh');

        $linkRepo = $this->createMock(CalendarLinkRepository::class);
        $linkRepo->method('findByProfileAndProvider')->willReturn($link);
        $mapRepo = $this->createMock(CalendarEventMappingRepository::class);
        $mapRepo->expects(self::never())->method('delete');

        $google = $this->createMock(CalendarProvider::class);
        $google->method('refreshAccessToken')->willThrowException(new \RuntimeException('revoked'));
        $vault = $this->createMock(TokenVault::class);
        $vault->method('decrypt')->willReturn('dec-refresh');

        $remover = new CalendarEventRemover(
            $linkRepo,
            $mapRepo,
            new CalendarProviderResolver($google, $this->createMock(CalendarProvider::class)),
            $vault
        );

        $result = $remover->removeForProvider($profileId, 'google', [$mapping]);
        self::assertTrue($result->refreshFailed);
        self::assertSame(0, $result->deleted);
        self::assertSame(1, $result->failed);
    }

    public function testRemoveForProviderKeepsFailedMappings(): void
    {
        $profileId = new ProfileId('prof-1');
        $mapping = CalendarEventMapping::create('dose-1', 'google', 'ext-1');
        [$remover, $mapRepo, $google] = $this->removerWithActiveLink($profileId);

        $google->method('deleteEvent')->willThrowException(new \RuntimeException('Microsoft Graph API delete failed with status 500.'));
        $mapRepo->expects(self::never())->method('delete');

        $result = $remover->removeForProvider($profileId, 'google', [$mapping]);
        self::assertFalse($result->refreshFailed);
        self::assertSame(0, $result->deleted);
        self::assertSame(1, $result->failed);
    }

    public function testRemoveForProviderDeletesWhenRemoteSucceeds(): void
    {
        $profileId = new ProfileId('prof-1');
        $mapping = CalendarEventMapping::create('dose-1', 'google', 'ext-1');
        [$remover, $mapRepo, $google] = $this->removerWithActiveLink($profileId);

        $google->expects(self::once())->method('deleteEvent')->with('access-token', 'ext-1');
        $mapRepo->expects(self::once())->method('delete')->with($mapping);

        $result = $remover->removeForProvider($profileId, 'google', [$mapping]);
        self::assertFalse($result->refreshFailed);
        self::assertSame(1, $result->deleted);
        self::assertSame(0, $result->failed);
    }

    public function testRemoveForProviderStillAttemptsWhenReauthRequired(): void
    {
        $profileId = new ProfileId('prof-1');
        $mapping = CalendarEventMapping::create('dose-1', 'google', 'ext-1');
        $link = CalendarLink::create($profileId, 'google', 'enc-refresh');
        $link->markReauthorizationRequired();

        $linkRepo = $this->createMock(CalendarLinkRepository::class);
        $linkRepo->method('findByProfileAndProvider')->willReturn($link);
        $mapRepo = $this->createMock(CalendarEventMappingRepository::class);
        $mapRepo->expects(self::once())->method('delete')->with($mapping);

        $google = $this->createMock(CalendarProvider::class);
        $google->method('refreshAccessToken')->willReturn(new CalendarOAuthTokens('access-token', null));
        $google->expects(self::once())->method('deleteEvent')->with('access-token', 'ext-1');
        $vault = $this->createMock(TokenVault::class);
        $vault->method('decrypt')->willReturn('dec-refresh');

        $remover = new CalendarEventRemover(
            $linkRepo,
            $mapRepo,
            new CalendarProviderResolver($google, $this->createMock(CalendarProvider::class)),
            $vault
        );

        $result = $remover->removeForProvider($profileId, 'google', [$mapping]);
        self::assertSame(1, $result->deleted);
        self::assertFalse($result->refreshFailed);
    }

    public function testRemoveForProviderEmptyMappings(): void
    {
        $mapRepo = $this->createMock(CalendarEventMappingRepository::class);
        $mapRepo->expects(self::never())->method('flush');

        $remover = new CalendarEventRemover(
            $this->createMock(CalendarLinkRepository::class),
            $mapRepo,
            new CalendarProviderResolver($this->createMock(CalendarProvider::class), $this->createMock(CalendarProvider::class)),
            $this->createMock(TokenVault::class)
        );

        $result = $remover->removeForProvider(new ProfileId('prof-1'), 'google', []);
        self::assertSame(0, $result->deleted);
        self::assertSame(0, $result->failed);
        self::assertFalse($result->refreshFailed);
    }

    /**
     * @return array{0: CalendarEventRemover, 1: CalendarEventMappingRepository&MockObject, 2: CalendarProvider&MockObject}
     */
    private function removerWithActiveLink(ProfileId $profileId): array
    {
        $link = CalendarLink::create($profileId, 'google', 'enc-refresh');
        $linkRepo = $this->createMock(CalendarLinkRepository::class);
        $linkRepo->method('findByProfileAndProvider')->with($profileId, 'google')->willReturn($link);

        $mapRepo = $this->createMock(CalendarEventMappingRepository::class);

        $google = $this->createMock(CalendarProvider::class);
        $google->method('refreshAccessToken')->with('dec-refresh')->willReturn(new CalendarOAuthTokens('access-token', null));

        $vault = $this->createMock(TokenVault::class);
        $vault->method('decrypt')->with('enc-refresh')->willReturn('dec-refresh');

        $remover = new CalendarEventRemover(
            $linkRepo,
            $mapRepo,
            new CalendarProviderResolver($google, $this->createMock(CalendarProvider::class)),
            $vault
        );

        return [$remover, $mapRepo, $google];
    }
}
