<?php

declare(strict_types=1);

namespace App\Tests\Identity\Application;

use Identity\Application\Command\AuthenticateCommand;
use Identity\Application\Command\AuthenticateHandler;
use Identity\Application\Command\RefreshTokenCommand;
use Identity\Application\Command\RefreshTokenHandler;
use Identity\Domain\Account;
use Identity\Domain\AccountRepository;
use Identity\Domain\ExternalUser;
use Identity\Domain\GoogleIdentityProvider;
use Identity\Domain\MicrosoftIdentityProvider;
use Identity\Domain\RefreshToken;
use Identity\Domain\RefreshTokenRepository;
use Identity\Domain\ValueObject\RefreshTokenId;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Failure;
use Shared\Domain\Result;
use Shared\Domain\ValueObject\Email;
use Shared\Domain\ValueObject\UserId;
use Shared\Infrastructure\Security\JwtService;

final class IdentityHandlersTest extends TestCase
{
    public function testAuthenticateNewUserSuccess(): void
    {
        $accountRepo = $this->createMock(AccountRepository::class);
        $refreshRepo = $this->createMock(RefreshTokenRepository::class);
        $google = $this->createMock(GoogleIdentityProvider::class);
        $microsoft = $this->createMock(MicrosoftIdentityProvider::class);
        $jwt = new JwtService('test-secret');

        $extUser = new ExternalUser('ext-123', 'newuser@example.com', 'New User');
        $google->method('verifyToken')->with('valid-token')->willReturn(Result::success($extUser));

        $accountRepo->method('findLinked')->willReturn(null);
        $accountRepo->method('findByEmail')->willReturn(null);
        $accountRepo->expects(self::once())->method('save');
        $accountRepo->expects(self::once())->method('saveLink');
        $refreshRepo->expects(self::once())->method('save');

        $handler = new AuthenticateHandler($accountRepo, $refreshRepo, $google, $microsoft, $jwt);
        $res = $handler(new AuthenticateCommand('google', 'valid-token'));

        self::assertTrue($res->isSuccess());
        self::assertArrayHasKey('token', $res->getValue());
        self::assertArrayHasKey('refreshToken', $res->getValue());
    }

    public function testAuthenticateTokenVerificationFailed(): void
    {
        $accountRepo = $this->createMock(AccountRepository::class);
        $refreshRepo = $this->createMock(RefreshTokenRepository::class);
        $google = $this->createMock(GoogleIdentityProvider::class);
        $microsoft = $this->createMock(MicrosoftIdentityProvider::class);
        $jwt = new JwtService('test-secret');

        $google->method('verifyToken')->with('invalid-token')->willReturn(Result::failure(Failure::unauthorized('Invalid ID token')));

        $handler = new AuthenticateHandler($accountRepo, $refreshRepo, $google, $microsoft, $jwt);
        $res = $handler(new AuthenticateCommand('google', 'invalid-token'));

        self::assertTrue($res->isFailure());
    }

    public function testRefreshTokenSuccessAndFailure(): void
    {
        $accountRepo = $this->createMock(AccountRepository::class);
        $refreshRepo = $this->createMock(RefreshTokenRepository::class);
        $jwt = new JwtService('test-secret');

        $handler = new RefreshTokenHandler($accountRepo, $refreshRepo, $jwt);

        // Not found
        $refreshRepo->method('findByToken')->with('missing-token')->willReturn(null);
        $res = $handler(new RefreshTokenCommand('missing-token'));
        self::assertTrue($res->isFailure());

        // Expired
        $userId = UserId::generate();
        $expiredToken = new RefreshToken(RefreshTokenId::generate(), $userId, 'expired-token', new \DateTimeImmutable('-10 days'), new \DateTimeImmutable('-1 day'));
        $refreshRepoExp = $this->createMock(RefreshTokenRepository::class);
        $refreshRepoExp->method('findByToken')->with('expired-token')->willReturn($expiredToken);
        $refreshRepoExp->expects(self::once())->method('delete')->with($expiredToken);
        $handlerExp = new RefreshTokenHandler($accountRepo, $refreshRepoExp, $jwt);
        $res = $handlerExp(new RefreshTokenCommand('expired-token'));
        self::assertTrue($res->isFailure());

        // Valid
        $validToken = new RefreshToken(RefreshTokenId::generate(), $userId, 'valid-token', new \DateTimeImmutable('+30 days'), new \DateTimeImmutable());
        $account = new Account($userId, new Email('user@example.com'), new \DateTimeImmutable(), new \DateTimeImmutable());
        $refreshRepoValid = $this->createMock(RefreshTokenRepository::class);
        $accountRepoValid = $this->createMock(AccountRepository::class);
        $refreshRepoValid->method('findByToken')->with('valid-token')->willReturn($validToken);
        $accountRepoValid->method('findById')->with($userId)->willReturn($account);
        $refreshRepoValid->expects(self::once())->method('delete')->with($validToken);
        $refreshRepoValid->expects(self::once())->method('save');

        $handlerValid = new RefreshTokenHandler($accountRepoValid, $refreshRepoValid, $jwt);
        $res = $handlerValid(new RefreshTokenCommand('valid-token'));
        self::assertTrue($res->isSuccess());
        self::assertArrayHasKey('token', $res->getValue());
        self::assertArrayHasKey('refreshToken', $res->getValue());
    }
}
