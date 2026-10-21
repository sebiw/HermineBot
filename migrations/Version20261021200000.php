<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Update auf Doctrine DBAL 4: Der Spaltentyp "array" (PHP serialize) entfällt.
 * log.context wird nach JSON umgewandelt, außerdem entfallen die DC2Type-Kommentare,
 * die DBAL 4 nicht mehr verwendet, und die Tabelle des Failure-Transports bekommt den
 * kombinierten Index, den Symfony Messenger seit 6.3 anlegt.
 */
final class Version20261021200000 extends AbstractMigration
{
    private const BATCH_SIZE = 2000;

    public function getDescription(): string
    {
        return 'DBAL 4: log.context serialize -> JSON, remove DC2Type comments, messenger index';
    }

    public function isTransactional(): bool
    {
        // DDL-Befehle beenden in MySQL/MariaDB ohnehin jede Transaktion
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->connection->executeStatement('ALTER TABLE log ADD context_json JSON DEFAULT NULL');
        $this->convertContext('context', 'context_json', function (string $value): string {
            // Bereits JSON (z. B. von neuem Code zwischen Datei-Update und Migration geschrieben)
            if (str_starts_with($value, '[') || str_starts_with($value, '{')) {
                json_decode($value);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $value;
                }
            }
            $data = @unserialize($value, ['allowed_classes' => false]);
            if (!is_array($data)) {
                $data = ['unparsed' => $value];
            }
            return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        });
        $this->connection->executeStatement('ALTER TABLE log DROP context, CHANGE context_json context JSON DEFAULT NULL');

        $this->addSql('ALTER TABLE events CHANGE channel_target_payload channel_target_payload JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE log CHANGE created_date_time created_date_time DATETIME NOT NULL');
        $this->addSql('ALTER TABLE user CHANGE roles roles JSON NOT NULL');
        $this->addSql('DROP INDEX IDX_75EA56E016BA31DB ON messenger_messages');
        $this->addSql('DROP INDEX IDX_75EA56E0E3BD61CE ON messenger_messages');
        $this->addSql('DROP INDEX IDX_75EA56E0FB7336F0 ON messenger_messages');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
    }

    public function down(Schema $schema): void
    {
        $this->connection->executeStatement('ALTER TABLE log ADD context_serialized LONGTEXT DEFAULT NULL');
        $this->convertContext('context', 'context_serialized', function (string $value): string {
            $data = json_decode($value, true);
            return serialize(is_array($data) ? $data : []);
        });
        $this->connection->executeStatement("ALTER TABLE log DROP context, CHANGE context_serialized context LONGTEXT DEFAULT NULL COMMENT '(DC2Type:array)'");
        $this->addSql('DROP INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages');
        $this->addSql('CREATE INDEX IDX_75EA56E016BA31DB ON messenger_messages (delivered_at)');
        $this->addSql('CREATE INDEX IDX_75EA56E0E3BD61CE ON messenger_messages (available_at)');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0 ON messenger_messages (queue_name)');
    }

    /**
     * Wandelt eine Spalte stapelweise in eine andere um (ein UPDATE pro Stapel statt pro Zeile).
     */
    private function convertContext(string $from, string $to, callable $convert): void
    {
        $lastId = 0;
        $total = 0;
        do {
            $rows = $this->connection->fetchAllAssociative(
                sprintf('SELECT id, %s AS value FROM log WHERE id > ? AND %s IS NOT NULL ORDER BY id LIMIT %d', $from, $from, self::BATCH_SIZE),
                [$lastId]
            );
            if (empty($rows)) {
                break;
            }
            $cases = [];
            $params = [];
            $ids = [];
            foreach ($rows as $row) {
                $cases[] = 'WHEN ? THEN ?';
                $params[] = (int)$row['id'];
                $params[] = $convert((string)$row['value']);
                $ids[] = (int)$row['id'];
                $lastId = (int)$row['id'];
            }
            $this->connection->executeStatement(
                sprintf('UPDATE log SET %s = CASE id %s END WHERE id IN (%s)', $to, implode(' ', $cases), implode(',', $ids)),
                $params
            );
            $total += count($rows);
        } while (count($rows) === self::BATCH_SIZE);

        $this->write(sprintf('log.%s -> log.%s: %d Einträge umgewandelt', $from, $to, $total));
    }
}
