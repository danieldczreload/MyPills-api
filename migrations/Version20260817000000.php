<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260817000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add reminder_sent_at to dose_events with partial index, and in_app_banners_enabled + reminder_minutes_before to notification_preferences.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE dose_events ADD reminder_sent_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql("CREATE INDEX idx_pending_dose_reminders ON dose_events (scheduled_at) WHERE status = 'pending' AND reminder_sent_at IS NULL");

        $this->addSql('ALTER TABLE notification_preferences ADD in_app_banners_enabled BOOLEAN DEFAULT true NOT NULL');
        $this->addSql('ALTER TABLE notification_preferences ADD reminder_minutes_before INT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_pending_dose_reminders');
        $this->addSql('ALTER TABLE dose_events DROP reminder_sent_at');

        $this->addSql('ALTER TABLE notification_preferences DROP in_app_banners_enabled');
        $this->addSql('ALTER TABLE notification_preferences DROP reminder_minutes_before');
    }
}
