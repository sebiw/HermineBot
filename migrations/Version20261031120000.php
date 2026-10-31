<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Hintergrundaufgaben: Einstellungen und letzter Lauf; Index, damit das Aufräumen des großen Logs alte Zeilen schnell findet
 */
final class Version20261031120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Background jobs: job_state, index on log.created_date_time';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE job_state (name VARCHAR(100) NOT NULL, enabled TINYINT DEFAULT NULL, interval_minutes INT DEFAULT NULL, setting_value INT DEFAULT NULL, last_started_at DATETIME DEFAULT NULL, last_status VARCHAR(10) DEFAULT NULL, last_message VARCHAR(500) DEFAULT NULL, last_duration_ms INT DEFAULT NULL, PRIMARY KEY (name)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE INDEX idx_log_created_date_time ON log (created_date_time)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE job_state');
        $this->addSql('DROP INDEX idx_log_created_date_time ON log');
    }
}
