<?php

declare(strict_types=1);

namespace App\Tests\CalendarIntegration\Domain;

use CalendarIntegration\Domain\CalendarAuthorizationRequest;
use CalendarIntegration\Domain\CalendarEventMapping;
use CalendarIntegration\Domain\CalendarLink;
use CalendarIntegration\Domain\CalendarLinkStatus;
use CalendarIntegration\Domain\CalendarOAuthTokens;
use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\ProfileId;
use Shared\Domain\ValueObject\UserId;

final class CalendarDomainTest extends TestCase
{
    public function testCalendarOAuthTokens(): void
    {
        $tokens = new CalendarOAuthTokens('access-token', 'refresh-token');
        self::assertSame('access-token', $tokens->accessToken());
        self::assertSame('refresh-token', $tokens->refreshToken());

        $tokensWithoutRefresh = new CalendarOAuthTokens('access-token-only', null);
        self::assertSame('access-token-only', $tokensWithoutRefresh->accessToken());
        self::assertNull($tokensWithoutRefresh->refreshToken());
    }

    public function testCalendarOAuthTokensInvalidEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CalendarOAuthTokens('', null);
    }

    public function testCalendarOAuthTokensInvalidEmptyRefresh(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CalendarOAuthTokens('valid-access', '');
    }

    public function testCalendarLinkStatusAndMethods(): void
    {
        $profId = ProfileId::generate();
        $link = CalendarLink::create($profId, 'google', 'enc-token-123');

        self::assertTrue($link->profileId()->equals($profId));
        self::assertSame('google', $link->provider());
        self::assertSame('enc-token-123', $link->encryptedRefreshToken());
        self::assertSame(CalendarLinkStatus::ACTIVE, $link->status());

        $link->markReauthorizationRequired();
        self::assertSame(CalendarLinkStatus::REAUTH_REQUIRED, $link->status());

        $link->updateEncryptedRefreshToken('new-enc-token');
        self::assertSame('new-enc-token', $link->encryptedRefreshToken());

        $link->markActive();
        self::assertSame(CalendarLinkStatus::ACTIVE, $link->status());
    }

    public function testCalendarEventMapping(): void
    {
        $mapping = CalendarEventMapping::create('dose-123', 'google', 'ext-event-456');
        self::assertSame('dose-123', $mapping->doseEventId());
        self::assertSame('google', $mapping->provider());
        self::assertSame('ext-event-456', $mapping->externalEventId());

        $mapping->updateExternalEventId('updated-event-789');
        self::assertSame('updated-event-789', $mapping->externalEventId());
    }

    public function testCalendarAuthorizationRequest(): void
    {
        $userId = UserId::generate();
        $profileId = ProfileId::generate();
        $expires = new \DateTimeImmutable('+10 minutes');

        $req = CalendarAuthorizationRequest::create(
            $userId,
            $profileId,
            'microsoft',
            'hash-123',
            'challenge-456',
            $expires
        );

        self::assertTrue($req->accountId()->equals($userId));
        self::assertTrue($req->profileId()->equals($profileId));
        self::assertSame('microsoft', $req->provider());
        self::assertSame('hash-123', $req->stateHash());
        self::assertSame('challenge-456', $req->codeChallenge());
        self::assertSame($expires, $req->expiresAt());
        self::assertTrue($req->isUsable(new \DateTimeImmutable()));
        self::assertNull($req->usedAt());
    }
}
