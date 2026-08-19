<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260819120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add form and color_token to medications, timezone to patient_profiles, and create taxonomy_groups table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE medications ADD form VARCHAR(32) DEFAULT 'pill' NOT NULL");
        $this->addSql("ALTER TABLE medications ADD color_token VARCHAR(80) DEFAULT 'sky' NOT NULL");

        $this->addSql("ALTER TABLE patient_profiles ADD timezone VARCHAR(100) DEFAULT 'UTC' NOT NULL");

        $this->addSql("CREATE TABLE taxonomy_groups (
            id VARCHAR(36) NOT NULL,
            profile_id VARCHAR(36) NOT NULL,
            type VARCHAR(32) NOT NULL,
            name VARCHAR(120) NOT NULL,
            description TEXT DEFAULT NULL,
            icon_name VARCHAR(80) DEFAULT NULL,
            color_value BIGINT DEFAULT NULL,
            is_custom BOOLEAN DEFAULT true NOT NULL,
            client_id VARCHAR(36) DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )");
        $this->addSql("CREATE INDEX idx_taxonomy_groups_profile_id ON taxonomy_groups (profile_id)");
        $this->addSql("CREATE INDEX idx_taxonomy_groups_client_id ON taxonomy_groups (client_id)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DROP TABLE taxonomy_groups");
        $this->addSql("ALTER TABLE patient_profiles DROP timezone");
        $this->addSql("ALTER TABLE medications DROP color_token");
        $this->addSql("ALTER TABLE medications DROP form");
    }
}
