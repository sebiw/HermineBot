<?php

namespace App\Tests\Core;

use App\Core\MessageDecorator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

/**
 * Platzhalter aus externen Quellen ({%…%}): Fehler der Quelle dürfen nicht als Text in den Channel
 */
class MessageDecoratorCommandTest extends TestCase
{
    private static mixed $server = null;
    private static int $port = 0;
    private string $endpointsFile = '';

    public static function setUpBeforeClass(): void
    {
        if( !defined( 'BASE_PATH' ) ){
            define( 'BASE_PATH' , dirname( __DIR__ , 2 ) );
        }
        $socket = stream_socket_server( 'tcp://127.0.0.1:0' );
        self::$port = (int) substr( strrchr( stream_socket_get_name( $socket , false ) , ':' ) , 1 );
        fclose( $socket );
        self::$server = proc_open( [ PHP_BINARY , '-S' , '127.0.0.1:' . self::$port , __DIR__ . '/../fixtures/command-endpoint-router.php' ] , [ [ 'pipe' , 'r' ] , [ 'file' , '/dev/null' , 'w' ] , [ 'file' , '/dev/null' , 'w' ] ] , $pipes );
        for( $i = 0 ; $i < 50 && @fsockopen( '127.0.0.1' , self::$port ) === false ; $i++ ){
            usleep( 100000 );
        }
    }

    public static function tearDownAfterClass(): void
    {
        if( is_resource( self::$server ) ){
            proc_terminate( self::$server );
        }
    }

    protected function tearDown(): void
    {
        if( $this->endpointsFile !== '' && file_exists( BASE_PATH . $this->endpointsFile ) ){
            unlink( BASE_PATH . $this->endpointsFile );
        }
    }

    private function decorator( string $url ): MessageDecorator
    {
        $this->endpointsFile = '/var/test-endpoints-' . getmypid() . '.json';
        file_put_contents( BASE_PATH . $this->endpointsFile , json_encode( [ 'Report' => [ 'type' => 'REST_JSON' , 'url' => $url , 'payload' => [ 'user' => 'u' ] ] ] ) );
        return new MessageDecorator( new Container( new ParameterBag( [ 'app.message_command_api_endpoints' => $this->endpointsFile ] ) ) );
    }

    public function testInhaltWirdEingesetzt(): void
    {
        self::assertSame( 'Stand: Offene Meldungen: 3' , $this->decorator( 'http://127.0.0.1:' . self::$port . '/ok' )->replace( 'Stand: {%Report%}' ) );
    }

    public function testFehlerstatusDerQuelleWirftStattFehlertextZuPosten(): void
    {
        $this->expectException( \RuntimeException::class );
        $this->decorator( 'http://127.0.0.1:' . self::$port . '/unauthorized' )->replace( 'Stand: {%Report%}' );
    }

    public function testAntwortOhneInhaltWirft(): void
    {
        $this->expectException( \RuntimeException::class );
        $this->decorator( 'http://127.0.0.1:' . self::$port . '/no-content' )->replace( 'Stand: {%Report%}' );
    }

    public function testNichtErreichbareQuelleWirft(): void
    {
        $this->expectException( \RuntimeException::class );
        $this->decorator( 'http://127.0.0.1:1/ok' )->replace( 'Stand: {%Report%}' );
    }

    public function testTextOhneBefehlRuftNichtsAuf(): void
    {
        self::assertSame( 'Nur Text' , $this->decorator( 'http://127.0.0.1:1/ok' )->replace( 'Nur Text' ) );
    }
}
