<?php

declare(strict_types=1);

namespace App\Tests\Shared\Domain\ValueObject;

use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\Email;
use Shared\Domain\ValueObject\ProfileId;
use Shared\Domain\ValueObject\UserId;

final class ValueObjectsTest extends TestCase
{
    public function testUserIdCorrectness(): void
    {
        $idStr = '123e4567-e89b-12d3-a456-426614174000';
        $userId = new UserId($idStr);

        self::assertSame($idStr, $userId->value());
        self::assertSame($idStr, (string) $userId);

        $otherUserId = new UserId($idStr);
        self::assertTrue($userId->equals($otherUserId));

        $differentUserId = new UserId('different-id');
        self::assertFalse($userId->equals($differentUserId));
    }

    public function testUserIdGeneration(): void
    {
        $userId1 = UserId::generate();
        $userId2 = UserId::generate();

        self::assertNotEmpty($userId1->value());
        self::assertNotEquals($userId1->value(), $userId2->value());
        // Verify UUID v4 regex pattern
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $userId1->value()
        );
    }

    public function testUserIdCannotBeEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new UserId('');
    }

    public function testProfileIdCorrectness(): void
    {
        $idStr = '98765432-1234-5678-abcd-1234567890ab';
        $profileId = new ProfileId($idStr);

        self::assertSame($idStr, $profileId->value());
        self::assertSame($idStr, (string) $profileId);

        $otherProfileId = new ProfileId($idStr);
        self::assertTrue($profileId->equals($otherProfileId));

        $differentProfileId = new ProfileId('different-id');
        self::assertFalse($profileId->equals($differentProfileId));
    }

    public function testProfileIdGeneration(): void
    {
        $profileId1 = ProfileId::generate();
        $profileId2 = ProfileId::generate();

        self::assertNotEmpty($profileId1->value());
        self::assertNotEquals($profileId1->value(), $profileId2->value());
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $profileId1->value()
        );
    }

    public function testProfileIdCannotBeEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ProfileId('');
    }

    public function testEmailCorrectness(): void
    {
        $emailStr = 'test@example.com';
        $email = new Email($emailStr);

        self::assertSame($emailStr, $email->value());
        self::assertSame($emailStr, (string) $email);

        $otherEmail = new Email('TEST@example.com');
        self::assertTrue($email->equals($otherEmail));

        $differentEmail = new Email('other@example.com');
        self::assertFalse($email->equals($differentEmail));
    }

    public function testInvalidEmailThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Email('invalid-email-address');
    }
}
