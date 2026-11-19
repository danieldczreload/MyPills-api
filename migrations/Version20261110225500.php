<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261110225500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add notification channel switches (notifyAlarm, notifyPush, notifyCalendar) to schedules with legacy backfill.';
    }

    public function up(Schema $schema): void
    {
        // Backfill pre-existing schedules to preserve push reminders and avoid enabling
        // phone alarms unexpectedly on legacy rows.
        $this->addSql('ALTER TABLE schedules ADD notify_alarm BOOLEAN DEFAULT false NOT NULL, ADD notify_push BOOLEAN DEFAULT true NOT NULL, ADD notify_calendar BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('UPDATE schedules SET notify_calendar = true WHERE medication_id IN (SELECT m.id FROM medications m INNER JOIN calendar_links cl ON cl.profile_id = m.profile_id)');
        $this->addSql('ALTER TABLE schedules ALTER COLUMN notify_alarm SET DEFAULT true, ALTER COLUMN notify_push SET DEFAULT false, ALTER COLUMN notify_calendar SET DEFAULT false');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE schedules DROP notify_alarm, DROP notify_push, DROP notify_calendar');
    }
}
