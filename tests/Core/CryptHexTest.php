<?php

namespace App\Tests\Core;

use App\Core\Crypt;
use PHPUnit\Framework\TestCase;

/**
 * Hex-Umwandlung für die Verschlüsselung: Das Ergebnis darf sich beim Aufräumen nicht ändern
 */
class CryptHexTest extends TestCase
{
    public function testGeradeLaenge(): void
    {
        self::assertSame( 'AB' , Crypt::hexToBytes( '4142' ) );
        self::assertSame( '4142' , Crypt::bytesToHex( 'AB' ) );
    }

    public function testUngeradeLaengeNimmtErstesZeichenEinzeln(): void
    {
        // wie im Web-Client: bei ungerader Länge zählt das erste Zeichen allein
        self::assertSame( mb_chr( 0xA ) . 'A' , Crypt::hexToBytes( 'A41' ) );
    }

    public function testLeer(): void
    {
        self::assertSame( '' , Crypt::hexToBytes( '' ) );
    }

    public function testWerteUeber127WerdenAlsZeichenKodiert(): void
    {
        self::assertSame( mb_chr( 0xFF ) , Crypt::hexToBytes( 'ff' ) );
    }
}
