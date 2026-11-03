<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261018120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add failed attempts counter to events';
    }

    public function isTransactional(): bool
    {
        // Tabellenänderungen beenden in MySQL/MariaDB ohnehin jede Transaktion (sonst Deprecation „transaction is already committed“)
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE events ADD failed_attempts INT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE events DROP failed_attempts');
    }
}
