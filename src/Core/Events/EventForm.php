<?php

namespace App\Core\Events;

use App\Core\Config;
use App\Entity\Event;

/**
 * Eingaben des Formulars „Neues Event“ bzw. „Event bearbeiten“: lesen, prüfen und übernehmen.
 * Bei Fehlern bleibt das Event unverändert, das Formular zeigt die Eingaben erneut an.
 */
final class EventForm
{
    /** Format der Felder datetime-local bzw. date */
    const DATE_TIME_FORMAT = 'Y-m-d\TH:i';
    const DATE_FORMAT = 'Y-m-d';

    public function __construct(
        public string $channel = '',
        public string $conversationId = '',
        public string $text = '',
        public string $start = '',
        public string $interval = '',
        public string $until = '',
        public bool $resetDue = false,
    )
    {
    }

    public static function fromEvent( Event $event ): self
    {
        return new self(
            $event->getChannelTarget(),
            $event->getConversationIdFromPayload() ?? '',
            (string) $event->getText(),
            $event->getStartDateTime()?->format( self::DATE_TIME_FORMAT ) ?? '',
            $event->getDateInterval() ?? '',
            $event->getUntilDateTime()?->format( self::DATE_FORMAT ) ?? '',
        );
    }

    /**
     * @param array<string, mixed> $values Formularfelder (Request::request->all())
     */
    public static function fromInput( array $values ): self
    {
        $string = fn( string $key ): string => is_scalar( $values[ $key ] ?? null ) ? trim( (string) $values[ $key ] ) : '';
        return new self(
            $string( 'channel' ),
            $string( 'conversationId' ),
            str_replace( "\r\n" , "\n" , is_scalar( $values['text'] ?? null ) ? (string) $values['text'] : '' ),
            $string( 'start' ),
            $string( 'interval' ),
            $string( 'until' ),
            ( $values['resetDue'] ?? '' ) === '1',
        );
    }

    /**
     * Prüft die Eingaben und übernimmt sie in das Event. Neue Events beginnen immer beim Start („Übermittlung zurücksetzen“).
     * @return list<string> Fehler für die Anzeige; leer = übernommen
     */
    public function applyTo( Event $event , Config $config , \DateTime $now ): array
    {
        $errors = [];
        $isNew = $event->getId() === null;
        $placeholder = $config->getConversationChannelPlaceholderName();

        if( !in_array( $this->channel , $config->getAllowedChannelNames() , true ) ){
            $errors[] = 'Bitte einen freigegebenen Channel oder „Unterhaltung“ wählen.';
        } elseif( $this->channel === $placeholder && $this->conversationId === '' ){
            $errors[] = 'Bitte eine Unterhaltung auswählen oder ihre ID eintragen.';
        }
        if( trim( $this->text ) === '' ){
            $errors[] = 'Bitte einen Text eingeben.';
        }
        $start = self::parse( self::DATE_TIME_FORMAT , $this->start );
        if( $start === null ){
            $errors[] = 'Bitte „Erstmals“ mit Datum und Uhrzeit angeben, z. B. 14.11.2026 09:00.';
        }
        if( $this->interval !== '' && !array_key_exists( $this->interval , $config->getAllowedIntervals() ) ){
            $errors[] = 'Diese Wiederholung gibt es nicht.';
        }
        $until = null;
        if( $this->interval !== '' && $this->until !== '' ){
            $until = self::parse( self::DATE_FORMAT , $this->until );
            if( $until === null ){
                $errors[] = 'Das Datum bei „Bis“ ist ungültig.';
            } else {
                // Bis einschließlich dieses Tages
                $until->setTime( 23 , 59 , 59 );
                if( $start !== null && $until < $start ){
                    $errors[] = '„Bis“ liegt vor „Erstmals“.';
                }
            }
        }
        if( $errors !== [] || $start === null ){
            return $errors;
        }

        $event->setChannelTarget( $this->channel );
        $event->setConversationIdToPayload( $this->channel === $placeholder ? $this->conversationId : null );
        $event->setText( $this->text );
        $event->setStartDateTime( $start );
        if( $isNew || $this->resetDue ){
            $event->setDueDateTime( clone $start );
        }
        if( $this->interval !== '' ){
            $event->setDateInterval( $this->interval );
            $event->setUntilDateTime( $until );
        } else {
            $event->setDateInterval( null );
            $event->setUntilDateTime( null );
            // Einmaliges Event, das (wieder) in der Zukunft liegt: erneut senden
            if( $event->getDueDateTime() >= $now ){
                $event->setDoneDateTime( null );
            }
        }
        // Erneutes Speichern gibt ein nach Fehlversuchen gesperrtes Event wieder frei
        $event->resetFailedAttempts();
        return [];
    }

    private static function parse( string $format , string $value ): ?\DateTime
    {
        $date = \DateTime::createFromFormat( '!' . $format , $value );
        // Kein „31.02.“ o. Ä., das PHP still weiterrechnen würde
        return $date !== false && $date->format( $format ) === $value ? $date : null;
    }
}
