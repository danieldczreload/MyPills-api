<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 0 — initial schema.
 * Creates the messenger_messages table for the Doctrine async event transport.
 */
final class Version20260606200108 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase 0: create messenger_messages table for Doctrine async transport';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS messenger_messages (
                id                BIGSERIAL PRIMARY KEY,
                body              TEXT        NOT NULL,
                headers           TEXT        NOT NULL,
                queue_name        VARCHAR(190) NOT NULL,
                created_at        TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                available_at      TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                delivered_at      TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL
            )
        SQL);
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_75EA56E0FB7336F0 ON messenger_messages (queue_name)');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_75EA56E0E3BD61CE ON messenger_messages (available_at)');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_75EA56E016BA31DB ON messenger_messages (delivered_at)');
        $this->addSql('COMMENT ON COLUMN messenger_messages.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN messenger_messages.available_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN messenger_messages.delivered_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS messenger_messages');
    }
}
