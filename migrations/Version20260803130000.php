<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260803130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Encrypt device registration tokens and add deterministic lookup fingerprints.';
    }

    public function up(Schema $schema): void
    {
        $this->connection->executeStatement('ALTER TABLE device_tokens ALTER COLUMN token TYPE TEXT');
        $this->connection->executeStatement('ALTER TABLE device_tokens ADD IF NOT EXISTS token_hash VARCHAR(64) DEFAULT NULL');

        $secret = $this->encryptionSecret();
        $rows = $this->connection->fetchAllAssociative('SELECT id, token FROM device_tokens WHERE token_hash IS NULL');
        foreach ($rows as $row) {
            if (!is_string($row['id'] ?? null) || !is_string($row['token'] ?? null) || $row['token'] === '') {
                throw new \RuntimeException('Cannot encrypt an invalid device token row.');
            }

            $this->connection->update('device_tokens', [
                'token' => $this->encryptToken($row['token'], $secret),
                'token_hash' => $this->fingerprint($row['token'], $secret),
            ], ['id' => $row['id']]);
        }

        $this->addSql('DROP INDEX IF EXISTS UNIQ_794A60955F37A13B');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_794A6095B3BC57DA ON device_tokens (token_hash)');
        $this->addSql('ALTER TABLE device_tokens ALTER COLUMN token_hash SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        throw new \RuntimeException('The device token encryption migration cannot be safely reverted.');
    }

    private function encryptionSecret(): string
    {
        $secret = $_SERVER['OAUTH_TOKEN_ENCRYPTION_KEY'] ?? $_ENV['OAUTH_TOKEN_ENCRYPTION_KEY'] ?? getenv('OAUTH_TOKEN_ENCRYPTION_KEY');
        if (!is_string($secret) || strlen($secret) < 16) {
            throw new \RuntimeException('OAUTH_TOKEN_ENCRYPTION_KEY (min 16 chars) must be set to encrypt device tokens.');
        }

        return $secret;
    }

    private function encryptToken(string $plaintext, string $secret): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $key = hash('sha256', 'encryption:' . $secret, true);
        $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $key);

        return 'v1:' . base64_encode($nonce . $ciphertext);
    }

    private function fingerprint(string $plaintext, string $secret): string
    {
        $key = hash('sha256', 'fingerprint:' . $secret, true);

        return hash_hmac('sha256', $plaintext, $key);
    }
}
