<?php

namespace App\Tests\Core\Events;

use App\Core\Events\EventTextHtml;
use PHPUnit\Framework\TestCase;

/**
 * Text eines Events in der Übersicht
 */
class EventTextHtmlTest extends TestCase
{
    public function testFormatUndPlatzhalter(): void
    {
        self::assertSame(
            '<strong>Hallo</strong> <span class="event-placeholder" data-tooltip="{{CompanyName}}">Name der Firma</span>' . "\n\n" . '<em>Stand</em>: <span class="event-placeholder" data-tooltip="{%Report%}">{%Report%}</span>',
            EventTextHtml::render( "**Hallo** {{CompanyName}}\n\n\n\n_Stand_: {%Report%}" , [ '{{CompanyName}}' => 'Name der Firma' ] )
        );
    }

    public function testHtmlWirdMaskiert(): void
    {
        self::assertSame( '&lt;script&gt;alert(1)&lt;/script&gt;' , EventTextHtml::render( '<script>alert(1)</script>' , [] ) );
        self::assertSame( '<span class="event-placeholder" data-tooltip="{{X}}">&lt;b&gt;</span>' , EventTextHtml::render( '{{X}}' , [ '{{X}}' => '<b>' ] ) );
    }

    public function testUnterstrichImWortBleibt(): void
    {
        self::assertSame( 'T_ABC_TEST und snake_case' , EventTextHtml::render( 'T_ABC_TEST und snake_case' , [] ) );
    }
}
