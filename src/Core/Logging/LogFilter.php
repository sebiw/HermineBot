<?php

namespace App\Core\Logging;

use Symfony\Component\HttpFoundation\InputBag;

/**
 * Filter der Log-Seite aus der Adresse, damit sich eine Ansicht als Link weitergeben lässt. Unbrauchbare Werte werden
 * ignoriert: Eine von Hand geänderte Adresse zeigt dann eben mehr, aber keine Fehlerseite.
 */
final readonly class LogFilter
{
    const LEVEL_ROUTINE = 'routine';
    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR = 'error';

    /** Auswahl auf der Seite => Level im Log (PSR-3) */
    const LEVELS = [
        self::LEVEL_ROUTINE => [ 'debug' ],
        self::LEVEL_INFO => [ 'info' , 'notice' ],
        self::LEVEL_WARNING => [ 'warning' ],
        self::LEVEL_ERROR => [ 'error' , 'critical' , 'alert' , 'emergency' ],
    ];
    const LEVEL_LABELS = [
        self::LEVEL_ROUTINE => 'Routine',
        self::LEVEL_INFO => 'Info',
        self::LEVEL_WARNING => 'Warnung',
        self::LEVEL_ERROR => 'Fehler',
    ];
    /** Ohne Auswahl: alles außer Routine (erfolgreiche Anmeldungen) */
    const DEFAULT_LEVELS = [ self::LEVEL_INFO , self::LEVEL_WARNING , self::LEVEL_ERROR ];

    /**
     * @param string[] $levels keys of LEVELS
     */
    public function __construct(
        public ?\DateTimeImmutable $from = null ,
        public ?\DateTimeImmutable $to = null ,
        public string $channel = '' ,
        public array $levels = self::DEFAULT_LEVELS ,
        public string $text = '' ,
        public int $page = 1 ,
    )
    {
    }

    public static function fromQuery( InputBag $query ): self
    {
        $levels = $query->all()['level'] ?? [];
        $levels = array_values( array_intersect( array_keys( self::LEVELS ) , is_array( $levels ) ? array_filter( $levels , 'is_string' ) : [] ) );
        return new self(
            self::date( $query->all()['from'] ?? null ) ,
            self::date( $query->all()['to'] ?? null ) ,
            is_string( $query->all()['channel'] ?? null ) ? trim( $query->all()['channel'] ) : '' ,
            $levels === [] ? self::DEFAULT_LEVELS : $levels ,
            is_string( $query->all()['q'] ?? null ) ? trim( $query->all()['q'] ) : '' ,
            is_numeric( $query->all()['page'] ?? null ) ? max( 1 , (int) $query->all()['page'] ) : 1 ,
        );
    }

    /**
     * @return string[] log levels of the selection
     */
    public function logLevels(): array
    {
        return array_merge( ...array_map( fn( string $level ) => self::LEVELS[ $level ] , $this->levels ) );
    }

    public function isDefault(): bool
    {
        return $this->from === null && $this->to === null && $this->channel === '' && $this->text === '' && $this->levels === self::DEFAULT_LEVELS;
    }

    /**
     * Values for links to other pages of the same view
     * @return array<string, mixed>
     */
    public function toQuery(): array
    {
        return array_filter( [
            'from' => $this->from?->format( 'Y-m-d\TH:i' ) ,
            'to' => $this->to?->format( 'Y-m-d\TH:i' ) ,
            'channel' => $this->channel ,
            'level' => $this->levels === self::DEFAULT_LEVELS ? [] : $this->levels ,
            'q' => $this->text ,
        ] , fn( mixed $value ) => $value !== null && $value !== '' && $value !== [] );
    }

    /**
     * Pattern for LIKE: the search text literally, without wildcards from the user
     */
    public static function likePattern( string $text ): string
    {
        return '%' . addcslashes( mb_strtolower( $text ) , '\\%_' ) . '%';
    }

    public static function date( mixed $value ): ?\DateTimeImmutable
    {
        if( !is_string( $value ) || trim( $value ) === '' ){
            return null;
        }
        foreach( [ 'Y-m-d\TH:i:s' , 'Y-m-d\TH:i' , 'Y-m-d' ] AS $format ){
            $date = \DateTimeImmutable::createFromFormat( '!' . $format , trim( $value ) );
            $errors = \DateTimeImmutable::getLastErrors();
            if( $date !== false && ( $errors === false || ( $errors['warning_count'] === 0 && $errors['error_count'] === 0 ) ) ){
                return $date;
            }
        }
        return null;
    }
}
