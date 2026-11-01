<?php

namespace App\Core\Logging;

/**
 * Bereiche des Bots als Log-Channels. Neuer Bereich: hier eintragen und in config/services.yaml als logger.<name> anlegen.
 * „app“ und „stashcat“ behalten ihren Namen, damit das große Log nicht umgeschrieben werden muss.
 */
final class LogChannels
{
    const EVENTS = 'events';
    const STASHCAT = 'stashcat';
    const API = 'api';
    const JOBS = 'jobs';
    const NUTZER = 'nutzer';
    const SYSTEM = 'app';

    const LABELS = [
        self::EVENTS => 'Geplante Nachrichten',
        self::STASHCAT => 'Hermine',
        self::API => 'Schnittstelle',
        self::JOBS => 'Hintergrundaufgaben',
        self::NUTZER => 'Nutzer',
        self::SYSTEM => 'System',
    ];

    private const ROUTE_PREFIXES = [
        'api_' => self::API,
        'jobs' => self::JOBS,
        'user' => self::NUTZER,
        'events' => self::EVENTS,
        'execute_events' => self::EVENTS,
        'save_event' => self::EVENTS,
    ];

    public static function label( string $channel ): string
    {
        return self::LABELS[ $channel ] ?? ucfirst( $channel );
    }

    /**
     * Channel for a problem on a page, by the name of its route
     */
    public static function forRoute( ?string $route ): string
    {
        foreach( self::ROUTE_PREFIXES AS $prefix => $channel ){
            if( str_starts_with( (string) $route , $prefix ) ){
                return $channel;
            }
        }
        return self::SYSTEM;
    }
}
