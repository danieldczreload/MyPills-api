<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Security;

use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Security\SodiumTokenVault;

final class SodiumTokenVaultTest extends TestCase
{
    public function testEncryptsAndDecryptsWithoutStoringPlaintext(): void
    {
        $vault = new SodiumTokenVault('test-secret-0123456789abcdef');
        $encrypted = $vault->encrypt('refresh-token');

        self::assertStringStartsWith('v1:', $encrypted);
        self::assertStringNotContainsString('refresh-token', $encrypted);
        self::assertSame('refresh-token', $vault->decrypt($encrypted));
        self::assertNotSame($encrypted, $vault->encrypt('refresh-token'));
    }

    public function testDecryptsCiphertextWithAConfiguredPreviousSecret(): void
    {
        $encrypted = (new SodiumTokenVault('first-secret-0123456789'))->encrypt('refresh-token');

        self::assertSame(
            'refresh-token',
            (new SodiumTokenVault('second-secret-012345678', 'first-secret-0123456789'))->decrypt($encrypted)
        );
    }

    public function testRejectsCiphertextCreatedWithAnUnconfiguredSecret(): void
    {
        $encrypted = (new SodiumTokenVault('first-secret-0123456789'))->encrypt('refresh-token');

        $this->expectException(\InvalidArgumentException::class);
        (new SodiumTokenVault('second-secret-012345678'))->decrypt($encrypted);
    }

    public function testRejectsWeakSecret(): void
    {
        $this->expectException(\LogicException::class);
        new SodiumTokenVault('short');
    }

    public function testRejectsEmptySecret(): void
    {
        $this->expectException(\LogicException::class);
        new SodiumTokenVault('');
    }
}
