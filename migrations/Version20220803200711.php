<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220803200711 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'ADD the events table';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE events (id INT AUTO_INCREMENT NOT NULL, start_date_time DATETIME NOT NULL, due_date_time DATETIME NOT NULL, done_date_time DATETIME DEFAULT NULL, until_date_time DATETIME DEFAULT NULL, date_interval VARCHAR(255) DEFAULT NULL, transmissions_count INT NOT NULL, channel_target VARCHAR(255) NOT NULL, text LONGTEXT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE events');
    }
}
