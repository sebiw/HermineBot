<?php

namespace App\Tests\Entity;

use App\Entity\Event;
use PHPUnit\Framework\TestCase;

/**
 * Wann der Daemon ein Event sendet (Event::isDue), durchgespielt wie im Takt
 */
class EventDueTest extends TestCase
{
    /**
     * Spielt den Takt stündlich durch und liefert die Sendezeitpunkte, wie Daemon::tick() sie setzt
     * @return list<string>
     */
    private function sends( Event $event , string $from , string $until ): array
    {
        $sent = [];
        for( $now = new \DateTime( $from ) ; $now < new \DateTime( $until ) ; $now->modify( '+1 hour' ) ){
            if( !$event->isDue( $now ) ){
                continue;
            }
            $next = $event->calculateNextDueDateTime( clone $now );
            if( $next !== null ){
                $event->setDueDateTime( $next );
            }
            $event->setDoneDateTime( clone $now );
            $sent[] = $now->format( 'd.m.' );
        }
        return $sent;
    }

    public function testWiederkehrendEndetAmEnddatum(): void
    {
        $event = new Event();
        $event->setDateInterval( 'P7D' );
        $event->setDueDateTime( new \DateTime( '2026-11-01 18:00' ) );
        $event->setUntilDateTime( new \DateTime( '2026-11-10 23:59' ) );

        self::assertSame( [ '01.11.' , '08.11.' ] , $this->sends( $event , '2026-11-01 18:00' , '2026-12-01' ) );
        self::assertTrue( $event->eventDone() );
    }

    public function testFaelligkeitGenauAmEnddatumWirdNochGesendet(): void
    {
        $event = new Event();
        $event->setDateInterval( 'P7D' );
        $event->setDueDateTime( new \DateTime( '2026-11-01 18:00' ) );
        $event->setUntilDateTime( new \DateTime( '2026-11-08 18:00' ) );

        self::assertSame( [ '01.11.' , '08.11.' ] , $this->sends( $event , '2026-11-01 18:00' , '2026-12-01' ) );
    }

    public function testOhneEnddatumLaeuftWeiter(): void
    {
        $event = new Event();
        $event->setDateInterval( 'P7D' );
        $event->setDueDateTime( new \DateTime( '2026-11-01 18:00' ) );

        self::assertCount( 5 , $this->sends( $event , '2026-11-01 18:00' , '2026-12-01' ) );
        self::assertFalse( $event->eventDone() );
    }

    public function testEinmaligNurEinmal(): void
    {
        $event = new Event();
        $event->setDueDateTime( new \DateTime( '2026-11-01 18:00' ) );

        self::assertSame( [ '01.11.' ] , $this->sends( $event , '2026-11-01 00:00' , '2026-11-05' ) );
        self::assertTrue( $event->eventDone() );
    }

    public function testGesperrtNachFehlversuchen(): void
    {
        $event = new Event();
        $event->setDueDateTime( new \DateTime( '2026-11-01 18:00' ) );
        for( $i = 0 ; $i < Event::MAX_FAILED_ATTEMPTS ; $i++ ){
            $event->increaseFailedAttempts();
        }
        self::assertFalse( $event->isDue( new \DateTime( '2026-11-02' ) ) );
    }

    public function testPausiertWirdNichtGesendetUndHoltBeimFortsetzenNichtsNach(): void
    {
        $event = new Event();
        $event->setDateInterval( 'P7D' );
        $event->setDueDateTime( new \DateTime( '2026-11-01 18:00' ) );

        self::assertSame( [ '01.11.' ] , $this->sends( $event , '2026-11-01 18:00' , '2026-11-05' ) );
        $event->pause();
        self::assertSame( Event::STATE_PAUSED , $event->getState() );
        self::assertSame( [] , $this->sends( $event , '2026-11-05' , '2026-11-20' ) );

        // Fortsetzen am 20.11.: 08.11. und 15.11. verpasst, weiter am 22.11.
        $event->resume( new \DateTime( '2026-11-20 12:00' ) );
        self::assertSame( '2026-11-22 18:00' , $event->getDueDateTime()->format( 'Y-m-d H:i' ) );
        self::assertSame( Event::STATE_RUNNING , $event->getState() );
        self::assertSame( [ '22.11.' , '29.11.' ] , $this->sends( $event , '2026-11-20 12:00' , '2026-12-01' ) );
    }

    public function testFortsetzenVorDemTerminLaesstDieFaelligkeitStehen(): void
    {
        $event = new Event();
        $event->setDateInterval( 'P7D' );
        $event->setDueDateTime( new \DateTime( '2026-11-08 18:00' ) );
        $event->pause();
        $event->resume( new \DateTime( '2026-11-05 12:00' ) );
        self::assertSame( '2026-11-08 18:00' , $event->getDueDateTime()->format( 'Y-m-d H:i' ) );
    }

    public function testEinmaligPausiertSendetBeimFortsetzenNachVergangenemTermin(): void
    {
        $event = new Event();
        $event->setDueDateTime( new \DateTime( '2026-11-08 18:00' ) );
        $event->pause();
        self::assertSame( [] , $this->sends( $event , '2026-11-08 18:00' , '2026-11-10' ) );

        $now = new \DateTime( '2026-11-10 12:00' );
        self::assertTrue( $event->sendsOnResume( $now ) );
        $event->resume( $now );
        self::assertTrue( $event->isDue( $now ) );
    }

    public function testErledigtBleibtErledigtAuchWennPausiert(): void
    {
        $event = new Event();
        $event->setDueDateTime( new \DateTime( '2026-11-08 18:00' ) );
        $event->setDoneDateTime( new \DateTime( '2026-11-08 18:00' ) );
        $event->pause();
        self::assertSame( Event::STATE_DONE , $event->getState() );
    }

    public function testGesperrtGehtVorPausiertUndFortsetzenVersprichtKeinenVersand(): void
    {
        $event = new Event();
        $event->setDueDateTime( new \DateTime( '2026-11-08 18:00' ) );
        ( new \ReflectionProperty( Event::class , 'failedAttempts' ) )->setValue( $event , Event::MAX_FAILED_ATTEMPTS );
        $event->pause();
        self::assertSame( Event::STATE_FAILED , $event->getState() );
        self::assertFalse( $event->sendsOnResume( new \DateTime( '2026-11-10 12:00' ) ) );
    }

    public function testFortsetzenNachAbgelaufenemEnddatumIstErledigt(): void
    {
        $event = new Event();
        $event->setDateInterval( 'P7D' );
        $event->setDueDateTime( new \DateTime( '2026-11-08 18:00' ) );
        $event->setUntilDateTime( new \DateTime( '2026-11-15 23:59:59' ) );
        $event->pause();

        $now = new \DateTime( '2026-11-20 12:00' );
        self::assertTrue( $event->endsOnResume( $now ) );
        self::assertFalse( $event->endsOnResume( new \DateTime( '2026-11-10 12:00' ) ) );
        $event->resume( $now );
        self::assertSame( Event::STATE_DONE , $event->getState() );
    }

    public function testFortsetzenMitUngueltigemIntervallAendertNichts(): void
    {
        $event = new Event();
        $event->setDateInterval( 'PT0S' );
        $event->setDueDateTime( new \DateTime( '2026-11-08 18:00' ) );
        $event->pause();
        self::assertNull( $event->dueAfterResume( new \DateTime( '2026-11-10 12:00' ) ) );
        try {
            $event->resume( new \DateTime( '2026-11-10 12:00' ) );
            self::fail( 'Ausnahme erwartet' );
        } catch ( \Exception ){
        }
        self::assertTrue( $event->isPaused() );
        self::assertSame( '2026-11-08 18:00' , $event->getDueDateTime()->format( 'Y-m-d H:i' ) );
    }
}
