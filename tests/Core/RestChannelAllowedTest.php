<?php

namespace App\Tests\Core;

use App\Core\Config;
use PHPUnit\Framework\TestCase;

/**
 * REST darf nur freigegebene Channels beschreiben, nie Unterhaltungen über den Platzhalter
 */
class RestChannelAllowedTest extends TestCase
{
    private function config(): Config
    {
        return new Config( [ 'allowed_channel' => [ 'Test-Channel' , 'Stab' ] ] );
    }

    public function testFreigegebenerChannel(): void
    {
        self::assertTrue( $this->config()->isRestChannelAllowed( 'Stab' ) );
    }

    public function testNichtFreigegebenerChannel(): void
    {
        self::assertFalse( $this->config()->isRestChannelAllowed( 'Anderer Channel' ) );
        self::assertFalse( $this->config()->isRestChannelAllowed( 'stab' ) , 'Groß- und Kleinschreibung zählt' );
        self::assertFalse( $this->config()->isRestChannelAllowed( null ) );
    }

    public function testPlatzhalterFuerUnterhaltungenNie(): void
    {
        $config = $this->config();
        self::assertContains( $config->getConversationChannelPlaceholderName() , $config->getAllowedChannelNames() , 'Der Platzhalter steht in der Liste' );
        self::assertFalse( $config->isRestChannelAllowed( $config->getConversationChannelPlaceholderName() ) );
    }
}
