<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261119163000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add alarm_reminders_enabled preference to notification_preferences.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notification_preferences ADD alarm_reminders_enabled BOOLEAN DEFAULT true NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notification_preferences DROP alarm_reminders_enabled');
    }
}
