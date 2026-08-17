<?php

declare(strict_types=1);

namespace App\Tests\Identity\Domain;

use Identity\Domain\Account;
use Identity\Domain\AccountOAuthLink;
use Identity\Domain\ExternalUser;
use Identity\Domain\RefreshToken;
use Identity\Domain\ValueObject\RefreshTokenId;
use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\Email;
use Shared\Domain\ValueObject\UserId;

final class IdentityDomainTest extends TestCase
{
    public function testAccountPropertiesAndChangeEmail(): void
    {
        $userId = UserId::generate();
        $email = new Email('test@example.com');
        $account = Account::create($userId, $email);

        self::assertTrue($account->id()->equals($userId));
        self::assertTrue($account->email()->equals($email));

        $newEmail = new Email('updated@example.com');
        $account->changeEmail($newEmail);
        self::assertTrue($account->email()->equals($newEmail));
    }

    public function testAccountOAuthLink(): void
    {
        $userId = UserId::generate();
        $link = AccountOAuthLink::create($userId, 'google', 'ext-12345');

        self::assertTrue($link->accountId()->equals($userId));
        self::assertSame('google', $link->provider());
        self::assertSame('ext-12345', $link->externalId());
        self::assertNotEmpty($link->id()->value());
    }

    public function testRefreshTokenExpiration(): void
    {
        $userId = UserId::generate();
        $validToken = RefreshToken::create($userId, 'token-123');
        self::assertFalse($validToken->isExpired());
        self::assertSame('token-123', $validToken->token());

        $expiredToken = new RefreshToken(
            RefreshTokenId::generate(),
            $userId,
            'token-expired',
            new \DateTimeImmutable('-1 hour'),
            new \DateTimeImmutable('-10 days')
        );
        self::assertTrue($expiredToken->isExpired());
    }

    public function testExternalUser(): void
    {
        $user = new ExternalUser('ext-1', 'email@test.com', 'Test User');
        self::assertSame('ext-1', $user->externalId);
        self::assertSame('email@test.com', $user->email);
        self::assertSame('Test User', $user->name);
    }
}
