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
}
