<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add formations.period_type to distinguish semester- and trimester-based formations.
 */
final class Version20260704000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add period_type to formations (semester | trimester)';
    }

    public function up(Schema $schema): void
    {
        // Add with a default to backfill existing rows, then drop the DB default:
        // new rows always receive a value from the PeriodType enum property.
        $this->addSql("ALTER TABLE formations ADD period_type VARCHAR(20) DEFAULT 'semester' NOT NULL");
        $this->addSql('ALTER TABLE formations ALTER COLUMN period_type DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE formations DROP period_type');
    }
}
