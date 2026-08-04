<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260803151000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Track calendar authorization state for reauthorization UX.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE calendar_links ADD status VARCHAR(30) DEFAULT 'active' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE calendar_links DROP status');
    }
}
