<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260902130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop free-form dosage from medications; dose lives on schedules.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE medications DROP dosage');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE medications ADD dosage VARCHAR(255) DEFAULT '' NOT NULL");
    }
}
