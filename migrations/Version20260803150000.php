<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260803150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Persist short-lived PKCE calendar authorization requests.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE calendar_authorization_requests (id VARCHAR(36) NOT NULL, account_id VARCHAR(36) NOT NULL, profile_id VARCHAR(36) NOT NULL, provider VARCHAR(20) NOT NULL, state_hash VARCHAR(64) NOT NULL, code_challenge VARCHAR(128) NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_calendar_auth_request_state_hash ON calendar_authorization_requests (state_hash)');
        $this->addSql('CREATE INDEX idx_calendar_auth_request_expires_at ON calendar_authorization_requests (expires_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE calendar_authorization_requests');
    }
}
