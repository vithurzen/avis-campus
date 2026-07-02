<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260702000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add rating column to reviews table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reviews ADD rating INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reviews DROP COLUMN rating');
    }
}
