<?php

namespace App\Core\Job;

use Doctrine\DBAL\Connection;

/**
 * Löscht Log-Einträge, die älter als die eingestellte Aufbewahrung sind.
 * In Stapeln, damit die Tabelle nie lange gesperrt ist (der Takt schreibt jede Minute ins Log, die Tabelle wird groß).
 */
class LogCleanupJob implements BackgroundJob
{
    const KEEP_DAYS = 60;

    /** Zeilen je DELETE */
    const BATCH_SIZE = 5000;

    /** Höchstens so viele Stapel je Lauf – der Rest folgt beim nächsten Lauf */
    const MAX_BATCHES = 200;

    /** Das Gleiche, wenn jemand im Browser wartet */
    const MAX_BATCHES_INTERACTIVE = 20;

    public function __construct( private readonly Connection $connection )
    {
    }

    public function getName(): string
    {
        return 'log-cleanup';
    }

    public function getLabel(): string
    {
        return 'Log aufräumen';
    }

    public function getDescription(): string
    {
        return 'Löscht Einträge unter „Logs“, die älter als die eingestellte Aufbewahrung sind.';
    }

    public function getDefaultIntervalMinutes(): int
    {
        return 1440;
    }

    public function isEnabledByDefault(): bool
    {
        return true;
    }

    public function getSetting(): ?array
    {
        return [ 'label' => 'Aufbewahrung in Tagen' , 'default' => self::KEEP_DAYS , 'min' => 1 , 'max' => 3650 ];
    }

    public function run( ?int $setting , bool $interactive = false ): JobResult
    {
        $keepDays = $setting ?? self::KEEP_DAYS;
        $before = ( new \DateTimeImmutable() )->modify( sprintf( '-%d days' , $keepDays ) )->format( 'Y-m-d H:i:s' );
        $deleted = 0;
        $maxBatches = $interactive ? self::MAX_BATCHES_INTERACTIVE : self::MAX_BATCHES;
        for( $batch = 0 ; $batch < $maxBatches ; $batch++ ){
            $count = (int) $this->connection->executeStatement( 'DELETE FROM log WHERE created_date_time < ? LIMIT ' . self::BATCH_SIZE , [ $before ] );
            $deleted += $count;
            if( $count < self::BATCH_SIZE ){
                break;
            }
        }
        if( $deleted === 0 ){
            return JobResult::idle();
        }
        return JobResult::done( sprintf( '%d Einträge älter als %d Tage gelöscht%s' , $deleted , $keepDays , $batch >= $maxBatches ? ' (Rest beim nächsten Lauf)' : '' ) );
    }
}
