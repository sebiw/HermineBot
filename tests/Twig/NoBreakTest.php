<?php

namespace App\Tests\Twig;

use App\Twig\AppExtension;
use PHPUnit\Framework\TestCase;

/**
 * Datum mit Uhrzeit und Namen bleiben zusammen, auch in der Kartenansicht
 */
class NoBreakTest extends TestCase
{
    public function testGeschuetzteZeichen(): void
    {
        self::assertSame( "03.11.2026\u{00A0}20:44" , AppExtension::noBreak( '03.11.2026 20:44' ) );
        self::assertSame( "Muster\u{2011}Meier" , AppExtension::noBreak( 'Muster-Meier' ) );
        self::assertSame( '' , AppExtension::noBreak( null ) );
    }
}
