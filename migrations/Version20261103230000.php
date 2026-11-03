<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Alte Tabelle event_entry entfernen: Vorgänger von events, seit Jahren ungenutzt (zuletzt erledigt 2022, letzte Fälligkeit 2024),
 * kein Code und kein Fremdschlüssel verweist darauf. Sie ließ schema:validate dauerhaft „nicht synchron“ melden.
 * Produktiv vorher gesichert (16 Einträge, Datei im Wurzelverzeichnis des Webspace, siehe private Betriebsdoku).
 * IF EXISTS: In neu aufgebauten Datenbanken (z. B. bin/pruefen) gab es die Tabelle nie.
 */
final class Version20261103230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Alte Tabelle event_entry entfernen';
    }

    public function isTransactional(): bool
    {
        // Tabellenänderungen beenden in MySQL/MariaDB ohnehin jede Transaktion (sonst Deprecation „transaction is already committed“)
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS event_entry');
    }

    public function down(Schema $schema): void
    {
        // Nur der Aufbau, die Daten stehen in der Sicherung
        $this->addSql('CREATE TABLE IF NOT EXISTS event_entry (id INT AUTO_INCREMENT NOT NULL, dueDateTime DATETIME NOT NULL, doneDateTime DATETIME DEFAULT NULL, channelTarget VARCHAR(255) NOT NULL, text LONGTEXT NOT NULL, startDateTime DATETIME NOT NULL, untilDateTime DATETIME DEFAULT NULL, dateInterval VARCHAR(255) DEFAULT NULL, transmissionsCount INT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
    }
}
