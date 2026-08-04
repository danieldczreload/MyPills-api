<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260803152000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Encrypt legacy calendar refresh tokens at rest.';
    }

    public function up(Schema $schema): void
    {
        $secret = $this->encryptionSecret();
        $rows = $this->connection->fetchAllAssociative('SELECT id, refresh_token FROM calendar_links');

        foreach ($rows as $row) {
            if (!is_string($row['id'] ?? null) || !is_string($row['refresh_token'] ?? null) || $row['refresh_token'] === '') {
                throw new \RuntimeException('Cannot encrypt an invalid calendar link row.');
            }

            if (str_starts_with($row['refresh_token'], 'v1:')) {
                continue;
            }

            $this->connection->update('calendar_links', [
                'refresh_token' => $this->encryptToken($row['refresh_token'], $secret),
            ], ['id' => $row['id']]);
        }
    }

    public function down(Schema $schema): void
    {
        throw new \RuntimeException('The calendar token encryption migration cannot be safely reverted.');
    }

    private function encryptionSecret(): string
    {
        $secret = $_SERVER['OAUTH_TOKEN_ENCRYPTION_KEY'] ?? $_ENV['OAUTH_TOKEN_ENCRYPTION_KEY'] ?? getenv('OAUTH_TOKEN_ENCRYPTION_KEY');
        if (!is_string($secret) || strlen($secret) < 16) {
            throw new \RuntimeException('OAUTH_TOKEN_ENCRYPTION_KEY (min 16 chars) must be set to encrypt calendar tokens.');
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
}
