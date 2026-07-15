<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260701231749 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE account_oauth_links (id VARCHAR(36) NOT NULL, account_id VARCHAR(36) NOT NULL, provider VARCHAR(50) NOT NULL, external_id VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_provider_external ON account_oauth_links (provider, external_id)');
        $this->addSql('CREATE TABLE accounts (id VARCHAR(36) NOT NULL, email VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_CAC89EACE7927C74 ON accounts (email)');
        $this->addSql('CREATE TABLE calendar_event_mappings (id VARCHAR(36) NOT NULL, dose_event_id VARCHAR(36) NOT NULL, provider VARCHAR(50) NOT NULL, external_event_id VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_dose_event_provider ON calendar_event_mappings (dose_event_id, provider)');
        $this->addSql('CREATE TABLE calendar_links (id VARCHAR(36) NOT NULL, profile_id VARCHAR(36) NOT NULL, provider VARCHAR(50) NOT NULL, refresh_token TEXT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_profile_provider ON calendar_links (profile_id, provider)');
        $this->addSql('CREATE TABLE device_tokens (id VARCHAR(36) NOT NULL, account_id VARCHAR(36) NOT NULL, token VARCHAR(1000) NOT NULL, platform VARCHAR(50) NOT NULL, locale VARCHAR(10) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_794A60955F37A13B ON device_tokens (token)');
        $this->addSql('CREATE TABLE dose_events (id VARCHAR(36) NOT NULL, medication_id VARCHAR(36) NOT NULL, schedule_id VARCHAR(36) NOT NULL, scheduled_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, status VARCHAR(50) NOT NULL, taken_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, client_id VARCHAR(36) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_dose_events_client_id ON dose_events (client_id)');
        $this->addSql('CREATE TABLE medications (id VARCHAR(36) NOT NULL, profile_id VARCHAR(36) NOT NULL, name VARCHAR(255) NOT NULL, dosage VARCHAR(255) NOT NULL, instructions TEXT DEFAULT NULL, photo_url VARCHAR(1000) DEFAULT NULL, client_id VARCHAR(36) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_medications_client_id ON medications (client_id)');
        $this->addSql('CREATE TABLE notification_preferences (id VARCHAR(36) NOT NULL, account_id VARCHAR(36) NOT NULL, dose_reminders_enabled BOOLEAN NOT NULL, missed_dose_nudges_enabled BOOLEAN NOT NULL, refill_alerts_enabled BOOLEAN NOT NULL, weekly_streak_summaries_enabled BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_3CAA95B49B6B5FBA ON notification_preferences (account_id)');
        $this->addSql('CREATE TABLE patient_profiles (id VARCHAR(36) NOT NULL, account_id VARCHAR(36) NOT NULL, name VARCHAR(255) NOT NULL, birth_date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, gender VARCHAR(50) NOT NULL, photo_url VARCHAR(1000) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE refresh_tokens (id VARCHAR(36) NOT NULL, account_id VARCHAR(36) NOT NULL, token VARCHAR(255) NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9BACE7E15F37A13B ON refresh_tokens (token)');
        $this->addSql('CREATE TABLE schedules (id VARCHAR(36) NOT NULL, medication_id VARCHAR(36) NOT NULL, start_date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, end_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, client_id VARCHAR(36) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, type VARCHAR(255) NOT NULL, times_of_day JSON DEFAULT NULL, every_hours INT DEFAULT NULL, start_at VARCHAR(5) DEFAULT NULL, end_at VARCHAR(5) DEFAULT NULL, days_of_week JSON DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_schedules_client_id ON schedules (client_id)');
        $this->addSql('CREATE TABLE sync_tombstones (id VARCHAR(36) NOT NULL, profile_id VARCHAR(36) NOT NULL, entity_type VARCHAR(50) NOT NULL, entity_id VARCHAR(36) NOT NULL, deleted_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE account_oauth_links');
        $this->addSql('DROP TABLE accounts');
        $this->addSql('DROP TABLE calendar_event_mappings');
        $this->addSql('DROP TABLE calendar_links');
        $this->addSql('DROP TABLE device_tokens');
        $this->addSql('DROP TABLE dose_events');
        $this->addSql('DROP TABLE medications');
        $this->addSql('DROP TABLE notification_preferences');
        $this->addSql('DROP TABLE patient_profiles');
        $this->addSql('DROP TABLE refresh_tokens');
        $this->addSql('DROP TABLE schedules');
        $this->addSql('DROP TABLE sync_tombstones');
    }
}
