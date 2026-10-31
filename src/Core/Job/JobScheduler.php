<?php

namespace App\Core\Job;

use App\Core\DatabaseLogger;
use App\Core\FileLock;
use App\Entity\JobState;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Führt die fälligen Hintergrundaufgaben aus. daemon:tick ruft tick() jede Minute auf (Cron), jede Aufgabe hat ihren eigenen Takt.
 * Je Aufgabe nur ein Lauf gleichzeitig (flock, frei auch nach einem Absturz); ein Fehler einer Aufgabe hält die anderen nicht auf.
 */
class JobScheduler
{
    /** Pseudo-Aufgabe: letzter Takt von daemon:tick */
    const TICK = 'daemon:tick';
    /** Cron startet nicht sekundengenau – so wird ein Takt nicht um eine Minute verschoben */
    const TOLERANCE_SECONDS = 30;
    const MIN_INTERVAL = 1;
    const MAX_INTERVAL = 10080;

    /** @var array<string, BackgroundJob> */
    private array $jobs = [];

    /**
     * @param iterable<BackgroundJob> $jobs
     */
    public function __construct(
        #[AutowireIterator( BackgroundJob::TAG )] iterable $jobs ,
        private readonly ManagerRegistry $doctrine ,
        #[Autowire(service: 'logger.events')] private readonly DatabaseLogger $logger ,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir ,
    )
    {
        foreach( $jobs AS $job ){
            $this->jobs[ $job->getName() ] = $job;
        }
    }

    /**
     * @return array<string, BackgroundJob>
     */
    public function getJobs(): array
    {
        return $this->jobs;
    }

    public function getJob( string $name ): ?BackgroundJob
    {
        return $this->jobs[ $name ] ?? null;
    }

    public function getState( string $name ): ?JobState
    {
        return $this->manager()->find( JobState::class , $name );
    }

    public function isEnabled( BackgroundJob $job ): bool
    {
        return $this->getState( $job->getName() )?->getEnabled() ?? $job->isEnabledByDefault();
    }

    public function getIntervalMinutes( BackgroundJob $job ): int
    {
        return $this->getState( $job->getName() )?->getIntervalMinutes() ?? $job->getDefaultIntervalMinutes();
    }

    public function getSettingValue( BackgroundJob $job ): ?int
    {
        return $this->getState( $job->getName() )?->getSettingValue() ?? $job->getSetting()['default'] ?? null;
    }

    /**
     * Speichert nur Abweichungen vom Standard (sonst null, damit ein geänderter Standard im Code wirkt)
     * @throws \InvalidArgumentException mit Meldung für den Nutzer
     */
    public function configure( BackgroundJob $job , bool $enabled , int $intervalMinutes , ?int $settingValue ): void
    {
        if( $intervalMinutes < self::MIN_INTERVAL || $intervalMinutes > self::MAX_INTERVAL ){
            throw new \InvalidArgumentException( sprintf( 'Der Takt muss zwischen %d und %d Minuten liegen.' , self::MIN_INTERVAL , self::MAX_INTERVAL ) );
        }
        $setting = $job->getSetting();
        if( $setting !== null && ( $settingValue === null || $settingValue < $setting['min'] || $settingValue > $setting['max'] ) ){
            throw new \InvalidArgumentException( sprintf( '%s muss zwischen %d und %d liegen.' , $setting['label'] , $setting['min'] , $setting['max'] ) );
        }
        $this->stateOf( $job->getName() )->configure(
            $enabled === $job->isEnabledByDefault() ? null : $enabled ,
            $intervalMinutes === $job->getDefaultIntervalMinutes() ? null : $intervalMinutes ,
            $setting === null || $settingValue === $setting['default'] ? null : $settingValue
        );
        $this->manager()->flush();
    }

    /**
     * @return \DateTimeImmutable|null nächster Lauf frühestens, null = aus
     */
    public function getNextRunAt( BackgroundJob $job ): ?\DateTimeImmutable
    {
        if( !$this->isEnabled( $job ) ){
            return null;
        }
        $last = $this->getState( $job->getName() )?->getLastStartedAt();
        return $last === null ? new \DateTimeImmutable() : $last->modify( sprintf( '+%d minutes' , $this->getIntervalMinutes( $job ) ) );
    }

    public static function isDue( ?\DateTimeImmutable $lastStartedAt , int $intervalMinutes , \DateTimeImmutable $now ): bool
    {
        return $lastStartedAt === null
            || $now >= $lastStartedAt->modify( sprintf( '+%d seconds' , $intervalMinutes * 60 - self::TOLERANCE_SECONDS ) );
    }

    /**
     * Ein Takt: merkt sich den Zeitpunkt und führt alle fälligen, eingeschalteten Aufgaben aus. Wirft nichts – der Versand
     * in daemon:tick soll davon unberührt bleiben.
     */
    public function tick(): void
    {
        $now = new \DateTimeImmutable();
        try {
            $this->stateOf( self::TICK )->start( $now );
            $this->manager()->flush();
        } catch( \Throwable $exception ){
            $this->logError( 'Takt der Hintergrundaufgaben nicht gespeichert' , $exception->getMessage() );
        }
        foreach( $this->jobs AS $name => $job ){
            try {
                if( $this->isEnabled( $job ) && self::isDue( $this->getState( $name )?->getLastStartedAt() , $this->getIntervalMinutes( $job ) , $now ) ){
                    $this->run( $job );
                }
            } catch( \Throwable $exception ){
                $this->logError( sprintf( 'Hintergrundaufgabe „%s“ fehlgeschlagen' , $job->getLabel() ) , $exception->getMessage() );
            }
        }
    }

    public function isRunning( BackgroundJob $job ): bool
    {
        return FileLock::isLocked( $this->lockFile( $job ) );
    }

    /**
     * Führt eine Aufgabe sofort aus (auch wenn sie aus ist – „jetzt ausführen“ ist eine bewusste Handlung)
     * @param bool $interactive true = jemand wartet im Browser auf das Ergebnis
     * @return JobResult|null null = läuft gerade schon
     */
    public function run( BackgroundJob $job , bool $interactive = false ): ?JobResult
    {
        $lock = FileLock::acquire( $this->lockFile( $job ) );
        if( $lock === null ){
            return null;
        }
        try {
            $start = hrtime( true );
            try {
                $this->stateOf( $job->getName() )->start( new \DateTimeImmutable() );
                $this->manager()->flush();
                $result = $job->run( $this->getSettingValue( $job ) , $interactive );
            } catch( \Throwable $exception ){
                $result = JobResult::error( $exception->getMessage() );
            }
            $durationMs = intdiv( hrtime( true ) - $start , 1_000_000 );
            if( $result->status === JobState::STATUS_ERROR ){
                $this->logError( sprintf( 'Hintergrundaufgabe „%s“ fehlgeschlagen' , $job->getLabel() ) , (string) $result->message );
            } elseif( $result->status === JobState::STATUS_DONE ){
                $this->manager();
                $this->logger->info( sprintf( 'Hintergrundaufgabe „%s“: %s' , $job->getLabel() , $result->message ) );
            }
            $this->stateOf( $job->getName() )->finish( $result->status , $result->message , $durationMs );
            $this->manager()->flush();
            return $result;
        } finally {
            $lock->release();
        }
    }

    private function lockFile( BackgroundJob $job ): string
    {
        return sprintf( '%s/var/job-%s.lock' , $this->projectDir , $job->getName() );
    }

    /**
     * Der Manager, nach einem Datenbankfehler (geschlossen) ein neuer – auch der Logger holt ihn sich hier
     */
    private function manager(): EntityManagerInterface
    {
        $manager = $this->doctrine->getManagerForClass( JobState::class );
        if( !$manager->isOpen() ){
            $this->doctrine->resetManager();
            $manager = $this->doctrine->getManagerForClass( JobState::class );
        }
        return $manager;
    }

    private function logError( string $message , string $error ): void
    {
        try {
            $this->manager();
            $this->logger->error( $message , [ 'error' => $error ] );
        } catch( \Throwable ){
            error_log( sprintf( '%s: %s' , $message , $error ) );
        }
    }

    /**
     * Legt den Eintrag über die Datenbank an: Cron und Oberfläche können gleichzeitig den ersten anlegen
     */
    private function stateOf( string $name ): JobState
    {
        $manager = $this->manager();
        $manager->getConnection()->executeStatement( 'INSERT IGNORE INTO job_state (name) VALUES (?)' , [ $name ] );
        return $manager->find( JobState::class , $name );
    }
}
