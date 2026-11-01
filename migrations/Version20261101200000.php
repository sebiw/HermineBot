<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Log: Indizes für die Filter der Log-Seite. Bestehende Einträge bleiben, wie sie sind: Die Tabelle ist groß, und alter Code schreibt ungestört weiter.
 */
final class Version20261101200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Log: Indizes für Level und Channel';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_log_level_created ON log (level, created_date_time)');
        $this->addSql('CREATE INDEX idx_log_channel_created ON log (channel, created_date_time)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_log_level_created ON log');
        $this->addSql('DROP INDEX idx_log_channel_created ON log');
    }
}
