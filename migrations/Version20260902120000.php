<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260902120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add structured dose amount and unit to schedules for reminder notifications.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE schedules ADD dose_amount NUMERIC(12, 4) DEFAULT NULL');
        $this->addSql('ALTER TABLE schedules ADD dose_unit VARCHAR(16) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE schedules DROP dose_amount');
        $this->addSql('ALTER TABLE schedules DROP dose_unit');
    }
}
