<?php

declare(strict_types=1);

namespace App\Tests\Identity\Domain;

use Identity\Domain\ValueObject\OAuthLinkId;
use Identity\Domain\ValueObject\RefreshTokenId;
use PHPUnit\Framework\TestCase;

final class IdentityValueObjectsTest extends TestCase
{
    public function testOAuthLinkId(): void
    {
        $id = OAuthLinkId::generate();
        self::assertNotEmpty($id->value());
        self::assertSame($id->value(), (string) $id);

        $other = new OAuthLinkId($id->value());
        self::assertTrue($id->equals($other));

        $diff = OAuthLinkId::generate();
        self::assertFalse($id->equals($diff));
    }

    public function testOAuthLinkIdEmptyThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new OAuthLinkId('');
    }

    public function testRefreshTokenId(): void
    {
        $id = RefreshTokenId::generate();
        self::assertNotEmpty($id->value());
        self::assertSame($id->value(), (string) $id);

        $other = new RefreshTokenId($id->value());
        self::assertTrue($id->equals($other));

        $diff = RefreshTokenId::generate();
        self::assertFalse($id->equals($diff));
    }

    public function testRefreshTokenIdEmptyThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RefreshTokenId('');
    }
}
