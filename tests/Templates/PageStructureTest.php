<?php

namespace App\Tests\Templates;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

/**
 * Regeln aus dem Abschnitt „Erscheinungsbild (CI)“ in docs/bausteine/OBERFLAECHE.md des StAN-Managers, die sich am Quelltext
 * prüfen lassen (Gegenstück dort: tests/Templates/PageStructureTest.php). Sie sollen beim Bauen auffallen, nicht erst dem Nutzer.
 */
class PageStructureTest extends TestCase
{
    /** Seiten ohne gemeinsamen Kopf, begründet: Teile anderer Seiten und die Login-Seite */
    private const NO_HEADER_NEEDED = [ 'base.html.twig' , 'base.loggedin.html.twig' , 'security/' , 'default/_' ];

    public function testPagesHaveTheCommonHeader(): void
    {
        foreach( $this->templates() AS $name => $source ){
            if( !str_contains( $source , 'block body' ) || $this->exempt( $name ) ){
                continue;
            }
            $this->assertStringContainsString( 'class="report-header"' , $source , $name . ': Seite ohne einheitlichen Kopf (.report-header mit h2 und .report-subtitle)' );
            $this->assertStringNotContainsString( '<h2 class="text-center"' , $source , $name . ': Überschriften stehen links' );
        }
    }

    public function testOnlyOnePageTitlePerTemplate(): void
    {
        foreach( $this->templates() AS $name => $source ){
            if( substr_count( $source , 'class="report-header"' ) === 1 ){
                $this->assertSame( 1 , preg_match_all( '/<h2[ >]/' , substr( $source , (int) strpos( $source , 'class="report-header"' ) ) ) , $name . ': Unter dem Kopf stehen Zwischenüberschriften als h3 oder legend' );
            }
        }
    }

    public function testTrailComesBeforeTheHeader(): void
    {
        foreach( $this->templates() AS $name => $source ){
            $header = strpos( $source , 'class="report-header"' );
            $trail = strpos( $source , '_trail.html.twig' );
            $this->assertTrue( $header === false || $trail === false || $trail < $header , $name . ': Der Pfad gehört über die Überschrift' );
        }
    }

    public function testListActionsUseTheCommonButtons(): void
    {
        foreach( $this->templates() AS $name => $source ){
            // Aktionen in Listen: .icon-actions mit .icon-button wie im StAN-Manager (Fehler 2026-11-03: eigene Leiste, am Handy zerbrachen die Knöpfe)
            $this->assertDoesNotMatchRegularExpression( '/class="[^"]*\b(event|user)-actions\b/' , $source , $name . ': Aktionen in Listen über .icon-actions' );
        }
    }

    public function testNoJumpButtons(): void
    {
        foreach( $this->templates() AS $name => $source ){
            $this->assertDoesNotMatchRegularExpression( '/<a [^>]*class="[^"]*button-(secondary|primary)[^"]*"[^>]*>\s*(Zurück|Zur Übersicht|Zu den|Neu(es|er)? )/u' , $source , $name . ': Seitenwechsel gehören in die Navigation, Knöpfe lösen Aktionen aus' );
        }
    }

    public function testNoBrowserDialogs(): void
    {
        foreach( $this->templates() AS $name => $source ){
            $this->assertDoesNotMatchRegularExpression( '/\b(confirm|alert)\(\s*[\'"]/' , $source , $name . ': Rückfragen über data-confirm, Meldungen über appToast, nie confirm()/alert() des Browsers' );
        }
    }

    public function testTooltipsViaDataAttribute(): void
    {
        foreach( $this->templates() AS $name => $source ){
            // Ausnahme wie im StAN-Manager: die Versionsanzeige in der Kopfleiste
            $source = str_replace( 'title="Changelog anzeigen"' , '' , $source );
            $this->assertDoesNotMatchRegularExpression( '/<(?!title)[a-z]+[^>]*\stitle="/' , $source , $name . ': Tooltips über data-tooltip, nicht title' );
        }
    }

    public function testNoInlineStylesOrFixedColors(): void
    {
        foreach( $this->templates() AS $name => $source ){
            $this->assertDoesNotMatchRegularExpression( '/\sstyle="/' , $source , $name . ': Aussehen gehört in public/assets/main.css bzw. stan.css, nicht ins Template' );
        }
    }

    public function testMenuEntriesLiveInOnePlace(): void
    {
        foreach( $this->templates() AS $name => $source ){
            if( $name !== 'base.loggedin.html.twig' ){
                $this->assertStringNotContainsString( '<nav class="secondary' , $source , $name . ': Menüeinträge stehen in base.loggedin.html.twig' );
            }
        }
    }

    public function testDatesInTablesDoNotBreak(): void
    {
        foreach( $this->templates() AS $name => $source ){
            // Datum mit Uhrzeit in Tabellenzellen: |no_break (text-no-wrap hebt das gemeinsame CSS in der Kartenansicht auf)
            preg_match_all( '/<td[^>]*>((?:(?!<\/td>).)*)<\/td>/s' , $source , $cells );
            foreach( $cells[1] AS $cell ){
                preg_match_all( '/\|date(\([^)]*\))?(?!\|no_break)(?=\s*(\}\}|:|\?))/' , $cell , $dates );
                $this->assertSame( [] , array_filter( $dates[0] , fn( string $date ) => !str_contains( $date , "'d.m.Y'" ) ) , $name . ': Datum mit Uhrzeit in Tabellen mit |no_break' );
            }
        }
    }

    private function exempt( string $name ): bool
    {
        foreach( self::NO_HEADER_NEEDED AS $prefix ){
            if( str_starts_with( $name , $prefix ) ){
                return true;
            }
        }
        return false;
    }

    /**
     * @return array<string, string> Pfad unter templates/ → Inhalt
     */
    private function templates(): array
    {
        $templates = [];
        foreach( ( new Finder() )->files()->in( dirname( __DIR__ , 2 ) . '/templates' )->name( '*.twig' ) AS $file ){
            $templates[ str_replace( '\\' , '/' , $file->getRelativePathname() ) ] = $file->getContents();
        }
        return $templates;
    }
}
