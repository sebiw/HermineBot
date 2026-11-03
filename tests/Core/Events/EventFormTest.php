<?php

namespace App\Tests\Core\Events;

use App\Core\Config;
use App\Core\Events\EventForm;
use App\Entity\Event;
use PHPUnit\Framework\TestCase;

/**
 * Formular „Neues Event“/„Event bearbeiten“: Prüfen und Übernehmen der Eingaben
 */
class EventFormTest extends TestCase
{
    private function config(): Config
    {
        return new Config( [ 'allowed_channel' => [ 'Test' ] , 'allowed_intervals' => [ 'P1W' => 'Jede Woche' ] ] );
    }

    /**
     * @param array<string, string> $values
     */
    private function form( array $values ): EventForm
    {
        return EventForm::fromInput( $values + [ 'channel' => 'Test' , 'text' => 'Hallo' , 'start' => '2026-11-14T09:00' ] );
    }

    public function testNeuesEinmaligesEvent(): void
    {
        $event = new Event();
        self::assertSame( [] , $this->form( [] )->applyTo( $event , $this->config() , new \DateTime( '2026-11-03 20:00' ) ) );
        self::assertSame( 'Test' , $event->getChannelTarget() );
        self::assertSame( '2026-11-14 09:00' , $event->getDueDateTime()->format( 'Y-m-d H:i' ) );
        self::assertNull( $event->getDateInterval() );
        self::assertNull( $event->getConversationIdFromPayload() );
        self::assertSame( Event::STATE_PLANNED , $event->getState() );
    }

    public function testWiederkehrendBisEinschliesslichTag(): void
    {
        $event = new Event();
        self::assertSame( [] , $this->form( [ 'interval' => 'P1W' , 'until' => '2026-12-31' ] )->applyTo( $event , $this->config() , new \DateTime( '2026-11-03' ) ) );
        self::assertSame( 'P1W' , $event->getDateInterval() );
        self::assertSame( '2026-12-31 23:59:59' , $event->getUntilDateTime()?->format( 'Y-m-d H:i:s' ) );
        self::assertSame( Event::STATE_RUNNING , $event->getState() );
    }

    public function testFehlerLassenDasEventUnveraendert(): void
    {
        $event = new Event();
        $event->setText( 'Alt' );
        $errors = $this->form( [ 'channel' => 'Fremd' , 'text' => '  ' , 'start' => '31.02.2026' , 'interval' => 'P9X' ] )->applyTo( $event , $this->config() , new \DateTime() );
        self::assertCount( 4 , $errors );
        self::assertSame( 'Alt' , $event->getText() );
    }

    public function testUngueltigesDatumWirdNichtWeitergerechnet(): void
    {
        self::assertNotSame( [] , $this->form( [ 'start' => '2026-02-31T09:00' ] )->applyTo( new Event() , $this->config() , new \DateTime() ) );
    }

    public function testBisVorErstmals(): void
    {
        $errors = $this->form( [ 'interval' => 'P1W' , 'until' => '2026-11-01' ] )->applyTo( new Event() , $this->config() , new \DateTime() );
        self::assertSame( [ '„Bis“ liegt vor „Erstmals“.' ] , $errors );
    }

    public function testUnterhaltungBrauchtId(): void
    {
        $config = $this->config();
        $placeholder = $config->getConversationChannelPlaceholderName();
        self::assertSame( [ 'Bitte eine Unterhaltung auswählen oder ihre ID eintragen.' ] , $this->form( [ 'channel' => $placeholder ] )->applyTo( new Event() , $config , new \DateTime() ) );

        $event = new Event();
        self::assertSame( [] , $this->form( [ 'channel' => $placeholder , 'conversationId' => '966886' ] )->applyTo( $event , $config , new \DateTime() ) );
        self::assertSame( '966886' , $event->getConversationIdFromPayload() );
    }

    public function testChannelVerwirftAlteUnterhaltung(): void
    {
        $event = new Event();
        $event->setConversationIdToPayload( '966886' );
        $this->form( [ 'conversationId' => '966886' ] )->applyTo( $event , $this->config() , new \DateTime() );
        self::assertNull( $event->getConversationIdFromPayload() );
    }

    public function testBearbeitenBehaeltFaelligkeitOhneZuruecksetzen(): void
    {
        $event = new Event();
        ( new \ReflectionProperty( Event::class , 'id' ) )->setValue( $event , 5 );
        $event->setDueDateTime( new \DateTime( '2026-11-20 09:00' ) );
        $event->increaseFailedAttempts();

        $this->form( [ 'interval' => 'P1W' ] )->applyTo( $event , $this->config() , new \DateTime( '2026-11-03' ) );
        self::assertSame( '2026-11-20 09:00' , $event->getDueDateTime()->format( 'Y-m-d H:i' ) , 'ohne Zurücksetzen bleibt die nächste Übermittlung' );
        self::assertSame( 0 , $event->getFailedAttempts() , 'Speichern gibt ein gesperrtes Event wieder frei' );

        $this->form( [ 'interval' => 'P1W' , 'resetDue' => '1' ] )->applyTo( $event , $this->config() , new \DateTime( '2026-11-03' ) );
        self::assertSame( '2026-11-14 09:00' , $event->getDueDateTime()->format( 'Y-m-d H:i' ) );
    }

    public function testFormularAusEventUndZurueck(): void
    {
        $event = new Event();
        $this->form( [ 'interval' => 'P1W' , 'until' => '2026-12-31' ] )->applyTo( $event , $this->config() , new \DateTime() );
        $form = EventForm::fromEvent( $event );
        self::assertSame( [ 'Test' , '2026-11-14T09:00' , 'P1W' , '2026-12-31' ] , [ $form->channel , $form->start , $form->interval , $form->until ] );
    }
}
