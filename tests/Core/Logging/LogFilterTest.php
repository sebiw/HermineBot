<?php

namespace App\Tests\Core\Logging;

use App\Core\Logging\LogChannels;
use App\Core\Logging\LogFilter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\InputBag;

class LogFilterTest extends TestCase
{
    public function testDefaultHidesRoutine(): void
    {
        $filter = LogFilter::fromQuery( new InputBag() );
        $this->assertTrue( $filter->isDefault() );
        $this->assertNotContains( 'debug' , $filter->logLevels() );
        $this->assertContains( 'critical' , $filter->logLevels() );
        $this->assertSame( [] , $filter->toQuery() );
    }

    public function testValuesFromTheAddress(): void
    {
        $filter = LogFilter::fromQuery( new InputBag( [ 'from' => '2026-11-01T08:15' , 'to' => '2026-11-02' , 'channel' => ' events ' , 'level' => [ 'routine' , 'error' ] , 'q' => ' Channel ' , 'page' => '3' ] ) );
        $this->assertSame( '2026-11-01 08:15' , $filter->from?->format( 'Y-m-d H:i' ) );
        $this->assertSame( '2026-11-02 00:00' , $filter->to?->format( 'Y-m-d H:i' ) );
        $this->assertSame( 'events' , $filter->channel );
        $this->assertSame( [ 'routine' , 'error' ] , $filter->levels );
        $this->assertSame( 'Channel' , $filter->text );
        $this->assertSame( 3 , $filter->page );
        $this->assertFalse( $filter->isDefault() );
        $this->assertSame( [ 'from' => '2026-11-01T08:15' , 'to' => '2026-11-02T00:00' , 'channel' => 'events' , 'level' => [ 'routine' , 'error' ] , 'q' => 'Channel' ] , $filter->toQuery() );
    }

    public function testUnusableValuesAreIgnored(): void
    {
        $filter = LogFilter::fromQuery( new InputBag( [ 'from' => '31.02.' , 'to' => '2026-02-31' , 'level' => [ 'quatsch' ] , 'channel' => [ 'x' ] , 'q' => [ 'x' ] , 'page' => '-4' ] ) );
        $this->assertNull( $filter->from );
        $this->assertNull( $filter->to );
        $this->assertSame( LogFilter::DEFAULT_LEVELS , $filter->levels );
        $this->assertSame( '' , $filter->channel );
        $this->assertSame( '' , $filter->text );
        $this->assertSame( 1 , $filter->page );
    }

    public function testSearchTextIsLiteral(): void
    {
        $this->assertSame( '%100\\%\\_ä%' , LogFilter::likePattern( '100%_Ä' ) );
    }

    public function testChannelOfARoute(): void
    {
        $this->assertSame( LogChannels::API , LogChannels::forRoute( 'api_send_message' ) );
        $this->assertSame( LogChannels::JOBS , LogChannels::forRoute( 'jobs_run' ) );
        $this->assertSame( LogChannels::NUTZER , LogChannels::forRoute( 'user_edit' ) );
        $this->assertSame( LogChannels::EVENTS , LogChannels::forRoute( 'execute_events_run' ) );
        $this->assertSame( LogChannels::SYSTEM , LogChannels::forRoute( 'changelog' ) );
        $this->assertSame( LogChannels::SYSTEM , LogChannels::forRoute( null ) );
    }

    public function testEveryLoggerExists(): void
    {
        $services = (string) file_get_contents( dirname( __DIR__ , 3 ) . '/config/services.yaml' );
        foreach( array_keys( LogChannels::LABELS ) AS $channel ){
            $this->assertStringContainsString( 'logger.' . $channel . ':' , $services );
        }
    }
}
