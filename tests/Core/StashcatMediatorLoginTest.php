<?php

namespace App\Tests\Core;

use App\Core\Config;
use App\Core\StashcatMediator;
use App\Stashcat\ApiClient;
use App\Stashcat\CryptoBox;
use App\Stashcat\Responses\AuthCheckResponse;
use App\Stashcat\Responses\LoginResponse;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Gespeicherte Anmeldung: Ein Fehler von Hermine darf den Bot nicht dauerhaft blockieren
 */
class StashcatMediatorLoginTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/bot-login-test-' . getmypid() . '-' . uniqid();
        ( new Filesystem() )->mkdir( $this->dir . '/credentials' );
    }

    protected function tearDown(): void
    {
        ( new Filesystem() )->remove( $this->dir );
    }

    private function mediator( FakeLoginApi $api ): StashcatMediator
    {
        $crypto = ( new \ReflectionClass( CryptoBox::class ) )->newInstanceWithoutConstructor();
        return new StashcatMediator( new Config( [ 'data_path' => $this->dir . '/' ] ) , $api , $crypto );
    }

    public function testFehlerBeimAnmeldenWirdNichtGespeichertUndDerNaechsteLaufMeldetSichNeuAn(): void
    {
        $api = new FakeLoginApi();
        $api->loginOk = false;
        self::assertFalse( $this->mediator( $api )->login() );
        self::assertFileDoesNotExist( $this->dir . '/credentials/login-data.json' );

        unlink( $this->dir . '/credentials/login-failed-at' );   // Pause übersprungen, eigener Test unten
        $api->loginOk = true;
        self::assertTrue( $this->mediator( $api )->login() );
        self::assertSame( 2 , $api->loginCalls );
    }

    public function testNachAblehnungPauseOhneNeuenVersuch(): void
    {
        $api = new FakeLoginApi();
        $api->loginOk = false;
        self::assertFalse( $this->mediator( $api )->login() );
        $api->loginOk = true;
        $mediator = $this->mediator( $api );
        self::assertFalse( $mediator->login() , 'In der Pause wird Hermine nicht gefragt' );
        self::assertTrue( $mediator->isLoginPaused() );
        self::assertSame( 1 , $api->loginCalls );

        // Pause vorbei
        file_put_contents( $this->dir . '/credentials/login-failed-at' , (string) ( time() - StashcatMediator::LOGIN_RETRY_AFTER_SECONDS - 1 ) );
        self::assertTrue( $this->mediator( $api )->login() );
        self::assertFileDoesNotExist( $this->dir . '/credentials/login-failed-at' );
    }

    public function testKaputteGespeicherteAnmeldungFuehrtZuNeuerAnmeldung(): void
    {
        file_put_contents( $this->dir . '/credentials/login-data.json' , json_encode( [ 'status' => [ 'value' => 'ERROR' ] , 'payload' => [] ] ) );
        $api = new FakeLoginApi();
        self::assertTrue( $this->mediator( $api )->login() );
        self::assertSame( 1 , $api->loginCalls );
        self::assertSame( 0 , $api->checkCalls , 'Ohne gültigen Schlüssel gibt es nichts zu prüfen' );
    }

    public function testUnklareAntwortDerPruefungFuehrtZuNeuerAnmeldung(): void
    {
        $api = new FakeLoginApi();
        self::assertTrue( $this->mediator( $api )->login() );
        $api->checkPayload = [];   // ohne „success“
        self::assertTrue( $this->mediator( $api )->login() );
        self::assertSame( 2 , $api->loginCalls );
    }

    public function testPruefungMitEinsStattTrueGiltAlsGueltig(): void
    {
        $api = new FakeLoginApi();
        self::assertTrue( $this->mediator( $api )->login() );
        $api->checkPayload = [ 'success' => 1 ];
        self::assertTrue( $this->mediator( $api )->login() );
        self::assertSame( 1 , $api->loginCalls , 'keine neue Anmeldung bei jedem Takt' );
    }

    public function testUnterhaltungPerIdAlsZahlOderText(): void
    {
        $mediator = $this->mediator( new FakeLoginApi() );
        ( new \ReflectionProperty( StashcatMediator::class , 'conversations' ) )->setValue( $mediator , [ new \App\Stashcat\Entities\Conversation( [ 'id' => 966886 ] ) , new \App\Stashcat\Entities\Conversation( [ 'id' => '42' ] ) ] );
        self::assertNotNull( $mediator->getConversationById( '966886' ) );
        self::assertNotNull( $mediator->getConversationById( 42 ) );
        self::assertNull( $mediator->getConversationById( '7' ) );
    }

    public function testGueltigeGespeicherteAnmeldungWirdWiederverwendet(): void
    {
        $api = new FakeLoginApi();
        self::assertTrue( $this->mediator( $api )->login() );
        self::assertTrue( $this->mediator( $api )->login() );
        self::assertSame( 1 , $api->loginCalls );
    }
}

class FakeLoginApi extends ApiClient
{
    public int $loginCalls = 0;
    public int $checkCalls = 0;
    public bool $loginOk = true;
    /** @var array<string, mixed> */
    public array $checkPayload = [ 'success' => true ];

    public function __construct()
    {
    }

    public function login(): LoginResponse
    {
        $this->loginCalls++;
        return new LoginResponse( $this->loginOk
            ? [ 'status' => [ 'value' => 'OK' ] , 'payload' => [ 'client_key' => 'key' , 'userinfo' => [ 'id' => 1 ] ] ]
            : [ 'status' => [ 'value' => 'ERROR' , 'short_message' => 'x' , 'message' => 'Too many attempts' ] , 'payload' => [] ] );
    }

    public function check( string $client_key ): AuthCheckResponse
    {
        $this->checkCalls++;
        return new AuthCheckResponse( [ 'status' => [ 'value' => 'OK' ] , 'payload' => $this->checkPayload ] );
    }
}
