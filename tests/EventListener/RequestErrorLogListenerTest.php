<?php

namespace App\Tests\EventListener;

use App\EventListener\RequestErrorLogListener;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Unerwartete Fehler landen im Log (ohne Feldwerte), 404 nicht; ohne Datenbank in var/log/errors.log
 */
class RequestErrorLogListenerTest extends TestCase
{
    public function testErrorWithFieldNamesOnly(): void
    {
        $rows = [];
        $connection = $this->createMock( Connection::class );
        $connection->method( 'insert' )->willReturnCallback( function( string $table , array $row ) use ( &$rows ){ $rows[] = [ $table , $row ]; return 1; } );
        $listener = new RequestErrorLogListener( $connection , $this->createStub( Security::class ) , sys_get_temp_dir() );

        $request = Request::create( '/' , 'POST' , [ '_username' => 'admin' , '_password' => 'geheim' , '_csrf_token' => 'x' ] );
        $listener( $this->event( $request , new \RuntimeException( 'kaputt' ) ) );
        $listener( $this->event( Request::create( '/nix' ) , new NotFoundHttpException() ) );

        $this->assertCount( 1 , $rows );
        [ $table , $row ] = $rows[0];
        $this->assertSame( 'log' , $table );
        $this->assertSame( 'error' , $row['level'] );
        $this->assertSame( 'Unerwarteter Fehler bei ohne Anmeldung: kaputt' , $row['message'] );
        $this->assertStringContainsString( '"fields":["_username","_password"]' , $row['context'] );
        $this->assertStringNotContainsString( 'geheim' , $row['context'] );
        $this->assertStringContainsString( 'RuntimeException' , $row['context'] );
    }

    public function testFileWhenTheDatabaseIsGone(): void
    {
        $dir = sys_get_temp_dir() . '/bot-error-log-' . bin2hex( random_bytes( 4 ) );
        mkdir( $dir );
        $connection = $this->createStub( Connection::class );
        $connection->method( 'insert' )->willThrowException( new \RuntimeException( 'DB weg' ) );

        ( new RequestErrorLogListener( $connection , $this->createStub( Security::class ) , $dir ) )( $this->event( Request::create( '/' ) , new \LogicException( 'kaputt' ) ) );

        $line = (string) file_get_contents( $dir . '/errors.log' );
        $this->assertStringContainsString( 'Unerwarteter Fehler bei ohne Anmeldung: kaputt' , $line );
        $this->assertStringContainsString( 'DB weg' , $line );
        unlink( $dir . '/errors.log' );
        rmdir( $dir );
    }

    private function event( Request $request , \Throwable $exception ): ExceptionEvent
    {
        return new ExceptionEvent( $this->createStub( HttpKernelInterface::class ) , $request , HttpKernelInterface::MAIN_REQUEST , $exception );
    }
}
