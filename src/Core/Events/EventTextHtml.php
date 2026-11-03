<?php

namespace App\Core\Events;

/**
 * Text eines Events für die Übersicht: wie er grob in Hermine aussieht (Absätze, **fett**, _kursiv_),
 * Platzhalter als kleines Kästchen mit verständlichem Namen. Ergebnis ist sicheres HTML.
 * Gegenstück im Formular: Vorschau in templates/default/_event_script.html.twig (gleiche Regeln).
 */
final class EventTextHtml
{
    /**
     * @param array<string, string> $labels Platzhalter-Syntax ({{…}} bzw. {%…%}) => Name
     */
    public static function render( string $text , array $labels ): string
    {
        $chips = [];
        // Platzhalter zuerst durch Marken ersetzen, damit die Formatierung nicht in ihre Namen greift
        $text = preg_replace_callback( '/\{\{\w+\}\}|\{%[^%]+%\}/' , function( array $match ) use ( &$chips , $labels ){
            $chips[] = sprintf( '<span class="event-placeholder" data-tooltip="%s">%s</span>' , self::escape( $match[0] ) , self::escape( $labels[ $match[0] ] ?? $match[0] ) );
            return "\x01" . ( count( $chips ) - 1 ) . "\x01";
        } , str_replace( "\x01" , '' , $text ) ) ?? '';

        $html = self::escape( trim( preg_replace( "/\r\n?/" , "\n" , $text ) ?? '' ) );
        $html = preg_replace( "/\n{3,}/" , "\n\n" , $html ) ?? '';
        $html = preg_replace( '/\*\*(.+?)\*\*/s' , '<strong>$1</strong>' , $html ) ?? '';
        $html = preg_replace( '/(^|[\s(])_(\S(?:.*?\S)?)_(?=[\s.,:;!?)]|$)/m' , '$1<em>$2</em>' , $html ) ?? '';

        return preg_replace_callback( "/\x01(\d+)\x01/" , fn( array $match ) => $chips[ (int) $match[1] ] , $html ) ?? '';
    }

    private static function escape( string $text ): string
    {
        return htmlspecialchars( $text , ENT_QUOTES | ENT_SUBSTITUTE , 'UTF-8' );
    }
}
