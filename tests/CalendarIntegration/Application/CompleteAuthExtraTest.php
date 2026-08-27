<?php

declare(strict_types=1);

namespace App\Tests\CalendarIntegration\Application;

use CalendarIntegration\Application\CalendarProviderResolver;
use CalendarIntegration\Application\Command\CompleteCalendarAuthorizationCommand;
use CalendarIntegration\Application\Command\CompleteCalendarAuthorizationHandler;
use CalendarIntegration\Domain\CalendarAuthorizationRequest;
use CalendarIntegration\Domain\CalendarAuthorizationRequestRepository;
use CalendarIntegration\Domain\CalendarLinkRepository;
use CalendarIntegration\Domain\CalendarOAuthTokens;
use CalendarIntegration\Domain\CalendarProvider;
use PHPUnit\Framework\TestCase;
use Profile\Domain\PatientProfile;
use Profile\Domain\ProfileRepository;
use Shared\Domain\TokenVault;
use Shared\Domain\ValueObject\ProfileId;
use Shared\Domain\ValueObject\UserId;

final class CompleteAuthExtraTest extends TestCase
{
    public function testCompleteAuthCodeExchangeFails(): void
    {
        $authRepo = $this->createMock(CalendarAuthorizationRequestRepository::class);
        $linkRepo = $this->createMock(CalendarLinkRepository::class);
        $profileRepo = $this->createMock(ProfileRepository::class);
        $vault = $this->createMock(TokenVault::class);
        $google = $this->createMock(CalendarProvider::class);
        $microsoft = $this->createMock(CalendarProvider::class);
        $resolver = new CalendarProviderResolver($google, $microsoft);

        $profile = new PatientProfile(new ProfileId('prof-1'), new UserId('acc-1'), 'Name', new \DateTimeImmutable('1990-01-01'), 'male', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $profileRepo->method('findById')->willReturn($profile);

        $verifier = str_repeat('v', 43);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        $authReq = CalendarAuthorizationRequest::create(new UserId('acc-1'), new ProfileId('prof-1'), 'google', hash('sha256', 'state-1'), $challenge, new \DateTimeImmutable('+5 minutes'));

        $authRepo->method('findByStateHash')->willReturn($authReq);
        $authRepo->method('consume')->willReturn(true);
        $google->method('exchangeAuthorizationCode')->willThrowException(new \RuntimeException('HTTP 500'));

        $handler = new CompleteCalendarAuthorizationHandler($authRepo, $linkRepo, $profileRepo, $vault, $resolver);
        $res = $handler(new CompleteCalendarAuthorizationCommand('prof-1', 'acc-1', 'google', 'bad-code', 'state-1', $verifier));

        self::assertTrue($res->isFailure());
        self::assertSame('BAD_REQUEST', $res->getFailure()->getType());
    }

    public function testCompleteAuthNoRefreshTokenReturned(): void
    {
        $authRepo = $this->createMock(CalendarAuthorizationRequestRepository::class);
        $linkRepo = $this->createMock(CalendarLinkRepository::class);
        $profileRepo = $this->createMock(ProfileRepository::class);
        $vault = $this->createMock(TokenVault::class);
        $google = $this->createMock(CalendarProvider::class);
        $microsoft = $this->createMock(CalendarProvider::class);
        $resolver = new CalendarProviderResolver($google, $microsoft);

        $profile = new PatientProfile(new ProfileId('prof-1'), new UserId('acc-1'), 'Name', new \DateTimeImmutable('1990-01-01'), 'male', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $profileRepo->method('findById')->willReturn($profile);

        $verifier = str_repeat('v', 43);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        $authReq = CalendarAuthorizationRequest::create(new UserId('acc-1'), new ProfileId('prof-1'), 'google', hash('sha256', 'state-1'), $challenge, new \DateTimeImmutable('+5 minutes'));

        $authRepo->method('findByStateHash')->willReturn($authReq);
        $authRepo->method('consume')->willReturn(true);
        $google->method('exchangeAuthorizationCode')->willReturn(new CalendarOAuthTokens('access-only', null));
        $linkRepo->method('findByProfileAndProvider')->willReturn(null);

        $handler = new CompleteCalendarAuthorizationHandler($authRepo, $linkRepo, $profileRepo, $vault, $resolver);
        $res = $handler(new CompleteCalendarAuthorizationCommand('prof-1', 'acc-1', 'google', 'valid-code', 'state-1', $verifier));

        self::assertTrue($res->isFailure());
        self::assertStringContainsString('did not return a refresh token', $res->getFailure()->getMessage());
    }

    public function testServerAuthCodeExchangeSuccessAndFailures(): void
    {
        $authRepo = $this->createMock(CalendarAuthorizationRequestRepository::class);
        $linkRepo = $this->createMock(CalendarLinkRepository::class);
        $profileRepo = $this->createMock(ProfileRepository::class);
        $vault = $this->createMock(TokenVault::class);

        $google = new class () implements CalendarProvider, \CalendarIntegration\Domain\ServerAuthCodeExchanger {
            public bool $shouldThrow = false;
            public function authorizationUrl(string $state, string $codeChallenge): string
            {
                return 'url';
            }
            public function exchangeAuthorizationCode(string $code, string $codeVerifier): CalendarOAuthTokens
            {
                return new CalendarOAuthTokens('tok', 'ref');
            }
            public function refreshAccessToken(string $refreshToken): CalendarOAuthTokens
            {
                return new CalendarOAuthTokens('tok', 'ref');
            }
            public function upsertEvent(string $accessToken, string $title, \DateTimeImmutable $start, \DateTimeImmutable $end, string $description, ?string $externalEventId = null, ?string $idempotencyKey = null): string
            {
                return 'id';
            }
            public function deleteEvent(string $accessToken, string $externalEventId): void
            {
            }
            public function exchangeServerAuthCode(string $serverAuthCode): CalendarOAuthTokens
            {
                if ($this->shouldThrow) {
                    throw new \RuntimeException('Google error');
                }
                return new CalendarOAuthTokens('acc-tok', 'ref-tok');
            }
        };

        $microsoft = $this->createMock(CalendarProvider::class);
        $resolver = new CalendarProviderResolver($google, $microsoft);

        $profile = new PatientProfile(new ProfileId('prof-1'), new UserId('acc-1'), 'Name', new \DateTimeImmutable('1990-01-01'), 'male', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $profileRepo->method('findById')->willReturn($profile);

        // 1. Server auth code with Microsoft fails
        $handler = new CompleteCalendarAuthorizationHandler($authRepo, $linkRepo, $profileRepo, $vault, $resolver);
        $res = $handler(new CompleteCalendarAuthorizationCommand('prof-1', 'acc-1', 'microsoft', 'server-code', '', ''));
        self::assertTrue($res->isFailure());
        self::assertSame('Server auth code connect is only supported for Google.', $res->getFailure()->getMessage());

        // 2. Server auth code with Google success
        $vault->method('encrypt')->with('ref-tok')->willReturn('enc-ref-tok');
        $linkRepo->expects(self::once())->method('save');

        $res = $handler(new CompleteCalendarAuthorizationCommand('prof-1', 'acc-1', 'google', 'server-code', '', ''));
        self::assertTrue($res->isSuccess());
        self::assertTrue($res->getValue()['connected']);

        // 3. Server auth code exchange throws Throwable
        $google->shouldThrow = true;
        $res2 = $handler(new CompleteCalendarAuthorizationCommand('prof-1', 'acc-1', 'google', 'server-code', '', ''));
        self::assertTrue($res2->isFailure());
        self::assertSame('BAD_REQUEST', $res2->getFailure()->getType());
    }

    public function testPKCEValidationFailures(): void
    {
        $authRepo = $this->createMock(CalendarAuthorizationRequestRepository::class);
        $linkRepo = $this->createMock(CalendarLinkRepository::class);
        $profileRepo = $this->createMock(ProfileRepository::class);
        $vault = $this->createMock(TokenVault::class);
        $google = $this->createMock(CalendarProvider::class);
        $microsoft = $this->createMock(CalendarProvider::class);
        $resolver = new CalendarProviderResolver($google, $microsoft);

        $profile = new PatientProfile(new ProfileId('prof-1'), new UserId('acc-1'), 'Name', new \DateTimeImmutable('1990-01-01'), 'male', null, new \DateTimeImmutable(), new \DateTimeImmutable());
        $profileRepo->method('findById')->willReturn($profile);

        $handler = new CompleteCalendarAuthorizationHandler($authRepo, $linkRepo, $profileRepo, $vault, $resolver);

        // Missing verifier
        $res = $handler(new CompleteCalendarAuthorizationCommand('prof-1', 'acc-1', 'google', 'code', 'state', ''));
        self::assertTrue($res->isFailure());

        // Invalid verifier characters
        $res = $handler(new CompleteCalendarAuthorizationCommand('prof-1', 'acc-1', 'google', 'code', 'state', 'short'));
        self::assertTrue($res->isFailure());

        // Request not found
        $authRepo->method('findByStateHash')->willReturn(null);
        $res = $handler(new CompleteCalendarAuthorizationCommand('prof-1', 'acc-1', 'google', 'code', 'state', str_repeat('v', 43)));
        self::assertTrue($res->isFailure());
        self::assertSame('Invalid or expired calendar authorization state.', $res->getFailure()->getMessage());
    }
}
