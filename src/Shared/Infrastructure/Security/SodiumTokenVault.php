<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Security;

use Shared\Domain\TokenVault;

final class SodiumTokenVault implements TokenVault
{
    private const VERSION = 'v1';

    /**
     * @var list<string>
     */
    private readonly array $encryptionKeys;

    /**
     * @var list<string>
     */
    private readonly array $fingerprintKeys;

    public function __construct(string $secret, string $previousSecrets = '')
    {
        if (trim($secret) === '') {
            throw new \LogicException('Token vault secret is not configured. Set OAUTH_TOKEN_ENCRYPTION_KEY.');
        }

        $secrets = [$secret];
        foreach (explode(',', $previousSecrets) as $previousSecret) {
            $previousSecret = trim($previousSecret);
            if ($previousSecret !== '' && !in_array($previousSecret, $secrets, true)) {
                $secrets[] = $previousSecret;
            }
        }

        foreach ($secrets as $configuredSecret) {
            if (strlen($configuredSecret) < 16) {
                throw new \LogicException('Token vault secrets must be at least 16 characters.');
            }
        }

        $this->encryptionKeys = array_map(
            static fn (string $configuredSecret): string => hash('sha256', 'encryption:' . $configuredSecret, true),
            $secrets
        );
        $this->fingerprintKeys = array_map(
            static fn (string $configuredSecret): string => hash('sha256', 'fingerprint:' . $configuredSecret, true),
            $secrets
        );
    }

    public function encrypt(string $plaintext): string
    {
        if ($plaintext === '') {
            throw new \InvalidArgumentException('Cannot encrypt an empty token.');
        }

        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $this->encryptionKeys[0]);

        return self::VERSION . ':' . base64_encode($nonce . $ciphertext);
    }

    public function decrypt(string $ciphertext): string
    {
        [$version, $encoded] = array_pad(explode(':', $ciphertext, 2), 2, null);

        if ($version !== self::VERSION || !is_string($encoded) || $encoded === '') {
            throw new \InvalidArgumentException('Invalid encrypted token format.');
        }

        $decoded = base64_decode($encoded, true);
        $nonceLength = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;

        if ($decoded === false || strlen($decoded) <= $nonceLength) {
            throw new \InvalidArgumentException('Invalid encrypted token payload.');
        }

        foreach ($this->encryptionKeys as $encryptionKey) {
            $plaintext = sodium_crypto_secretbox_open(
                substr($decoded, $nonceLength),
                substr($decoded, 0, $nonceLength),
                $encryptionKey
            );

            if ($plaintext !== false) {
                return $plaintext;
            }
        }

        throw new \InvalidArgumentException('Encrypted token authentication failed.');
    }

    public function isEncrypted(string $value): bool
    {
        return str_starts_with($value, self::VERSION . ':');
    }

    public function fingerprint(string $plaintext): string
    {
        return $this->fingerprints($plaintext)[0];
    }

    /**
     * Returns fingerprints for the active key and configured previous keys so
     * lookups continue to work during key rotation.
     *
     * @return list<string>
     */
    public function fingerprints(string $plaintext): array
    {
        if ($plaintext === '') {
            throw new \InvalidArgumentException('Cannot fingerprint an empty token.');
        }

        return array_map(
            static fn (string $fingerprintKey): string => hash_hmac('sha256', $plaintext, $fingerprintKey),
            $this->fingerprintKeys
        );
    }
}
