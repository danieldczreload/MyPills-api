<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261006120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store the IANA timezone on a schedule. Null keeps using the profile timezone.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE schedules ADD timezone VARCHAR(100) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE schedules DROP timezone');
    }
}
