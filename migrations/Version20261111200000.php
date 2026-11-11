<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Events pausieren: neue Spalte mit Standard „nicht pausiert“, alter Code läuft unverändert weiter.
 */
final class Version20261111200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Events pausieren (Spalte paused)';
    }

    public function isTransactional(): bool
    {
        // Tabellenänderungen beenden in MySQL/MariaDB ohnehin jede Transaktion (sonst Deprecation „transaction is already committed“)
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE events ADD paused TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE events DROP paused');
    }
}
