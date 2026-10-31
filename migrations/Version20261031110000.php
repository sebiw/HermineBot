<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Queue entfernt: Tabelle des Failure-Transports entfällt. Liegen dort noch fehlgeschlagene Nachrichten, bleibt sie vorerst
 * stehen, ohne die weiteren Migrationen aufzuhalten – der nächste Migrationslauf versucht es erneut.
 */
final class Version20261031110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove messenger table of the failure transport';
    }

    public function up(Schema $schema): void
    {
        $this->skipIf( !$schema->hasTable('messenger_messages') , 'messenger_messages gibt es nicht.' );
        $count = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM messenger_messages');
        $this->skipIf( $count > 0 , sprintf('messenger_messages enthält noch %d Nachrichten (Spalte body) – Tabelle bleibt, bis sie leer ist.' , $count ) );
        $this->addSql('DROP TABLE messenger_messages');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }
}
