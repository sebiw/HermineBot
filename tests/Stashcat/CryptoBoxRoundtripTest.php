<?php

namespace App\Tests\Stashcat;

use App\Stashcat\Config;
use App\Stashcat\CryptoBox;
use App\Stashcat\Entities\Message;
use PHPUnit\Framework\TestCase;

/**
 * Verschlüsseln und Entschlüsseln wie beim Senden und Lesen in Hermine, ohne Hermine.
 * Sichert die Verschlüsselung beim Aufräumen ab: Das Ergebnis darf sich nicht ändern.
 */
class CryptoBoxRoundtripTest extends TestCase
{
    private const TEXTS = [
        'Hallo',
        'Dienst am Samstag: Ölwechsel, Übung „Brücke“ – 9 € Pfand 🚒',
        '**fett** und _kursiv_',
        "Zeile 1\nZeile 2",
        'genau sechzehn!!',
    ];

    public function testUnterhaltung(): void
    {
        $box = new CryptoBox( new Config( [] ) );
        $box->setConversationKey( random_bytes( CryptoBox::CHAT_ACCESS_KEY_LENGTH ) , '42' );
        foreach( self::TEXTS AS $text ){
            $iv = '';
            $encrypted = $box->getConversationMessageEncrypted( $text , '42' , $iv );
            self::assertNotSame( '' , $iv );
            $message = new Message( [ 'text' => $encrypted , 'iv' => $iv ] );
            self::assertSame( $text , $box->getConversationMessageDecrypted( $message , '42' ) , $text );
        }
    }

    public function testChannelMitSchluesselPerRsa(): void
    {
        $key = openssl_pkey_new( [ 'private_key_bits' => 2048 , 'private_key_type' => OPENSSL_KEYTYPE_RSA ] );
        self::assertNotFalse( $key );
        $details = openssl_pkey_get_details( $key );
        self::assertNotFalse( $details );
        $publicPem = $details['key'];
        $box = new CryptoBox( new Config( [] ) );
        // Den privaten Schlüssel setzt sonst setPrivateKeyFromResponse() nach dem Login
        ( new \ReflectionProperty( CryptoBox::class , 'privateKey' ) )->setValue( $box , $key );

        openssl_public_encrypt( random_bytes( 32 ) , $encryptedChannelKey , $publicPem , OPENSSL_PKCS1_OAEP_PADDING );
        $box->setChannelEncryptionChannel( base64_encode( $encryptedChannelKey ) , '7' );

        foreach( self::TEXTS AS $text ){
            $iv = '';
            $encrypted = $box->getChannelMessageEncrypted( $text , '7' , $iv );
            $message = new Message( [ 'text' => $encrypted , 'iv' => $iv ] );
            self::assertSame( $text , $box->getChannelMessageDecrypted( $message , '7' ) , $text );
        }
    }

    public function testFehlenderChannelSchluesselBrichtAb(): void
    {
        $box = new CryptoBox( new Config( [] ) );
        $iv = '';
        $this->expectException( \RuntimeException::class );
        $box->getChannelMessageEncrypted( 'x' , 'unbekannt' , $iv );
    }
}
