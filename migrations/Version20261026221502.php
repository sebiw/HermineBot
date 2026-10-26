<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Benutzer: Zeitpunkt der letzten Aktivität (Oberfläche bzw. REST-Aufruf), nur eine neue Spalte – abwärtskompatibel
 */
final class Version20261026221502 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'user.last_seen_at: last activity of a user (web or REST)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD last_seen_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP last_seen_at');
    }
}
