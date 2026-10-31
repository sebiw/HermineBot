<?php

namespace App\Core\Job;

use App\Entity\JobState;

/**
 * Ergebnis eines Laufs: Arbeit erledigt, nichts zu tun oder Fehler – mit kurzer Meldung für die Übersicht.
 */
final readonly class JobResult
{
    private function __construct( public string $status , public ?string $message )
    {
    }

    public static function done( string $message ): self
    {
        return new self( JobState::STATUS_DONE , $message );
    }

    public static function idle( ?string $message = null ): self
    {
        return new self( JobState::STATUS_IDLE , $message );
    }

    public static function error( string $message ): self
    {
        return new self( JobState::STATUS_ERROR , $message );
    }
}
