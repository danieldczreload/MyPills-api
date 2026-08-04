<?php

declare(strict_types=1);

namespace Shared\Domain;

interface TokenVault
{
    public function encrypt(string $plaintext): string;

    public function decrypt(string $ciphertext): string;

    public function isEncrypted(string $value): bool;

    public function fingerprint(string $plaintext): string;

    /**
     * @return list<string>
     */
    public function fingerprints(string $plaintext): array;
}
