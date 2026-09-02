<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260902190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add cancelled_at to schedules so cancel-recurring stops future expansion.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE schedules ADD cancelled_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE schedules DROP cancelled_at');
    }
}
