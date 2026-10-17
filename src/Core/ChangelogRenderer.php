<?php

namespace App\Core;

/**
 * Wandelt CHANGELOG.md (Keep-a-Changelog-Format) in HTML um.
 * Unterstützt nur den dort verwendeten Markdown-Umfang: Überschriften, Listen, Absätze, Links und Fettschrift.
 */
class ChangelogRenderer
{

    /**
     * @param string $markdown
     * @return string
     */
    public function render( string $markdown ): string
    {
        $html = [];
        $listOpen = false;
        $sections = $this->removeEmptySections( preg_split('/\R/' , $markdown ) );

        foreach( $sections AS $line ){
            $line = rtrim( $line );

            if( str_starts_with( $line , '- ' ) ){
                if( !$listOpen ){
                    $html[] = '<ul>';
                    $listOpen = true;
                }
                $html[] = '<li>' . $this->renderInline( substr( $line , 2 ) ) . '</li>';
                continue;
            }

            if( $listOpen ){
                $html[] = '</ul>';
                $listOpen = false;
            }

            if( $line === '' || str_starts_with( $line , '# ' ) ){
                // Hauptüberschrift wird vom Dialog-Titel ersetzt
                continue;
            } elseif( str_starts_with( $line , '### ' ) ){
                $html[] = '<h4>' . $this->renderInline( substr( $line , 4 ) ) . '</h4>';
            } elseif( str_starts_with( $line , '## ' ) ){
                $html[] = '<h3>' . $this->renderInline( substr( $line , 3 ) ) . '</h3>';
            } else {
                $html[] = '<p>' . $this->renderInline( $line ) . '</p>';
            }
        }

        if( $listOpen ){
            $html[] = '</ul>';
        }

        return implode("\n" , $html );
    }

    /**
     * Entfernt Versions-Abschnitte ohne Einträge (z. B. ein leeres "Unreleased")
     * @param string[] $lines
     * @return string[]
     */
    protected function removeEmptySections( array $lines ): array
    {
        $result = [];
        $section = [];
        $sectionHasContent = true;

        foreach( $lines AS $line ){
            if( str_starts_with( $line , '## ' ) ){
                if( $sectionHasContent ){
                    $result = array_merge( $result , $section );
                }
                $section = [];
                $sectionHasContent = false;
            } elseif( trim( $line ) !== '' ){
                $sectionHasContent = true;
            }
            $section[] = $line;
        }

        if( $sectionHasContent ){
            $result = array_merge( $result , $section );
        }

        return $result;
    }

    /**
     * @param string $text
     * @return string
     */
    protected function renderInline( string $text ): string
    {
        $text = htmlspecialchars( $text , ENT_QUOTES , 'UTF-8' );

        // Versionsangaben "[1.0.0]" ohne Link-Ziel
        $text = preg_replace('/\[([^\]]+)\](?!\()/' , '$1' , $text );
        // Links [Text](https://...)
        $text = preg_replace('/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/' , '<a href="$2" target="_blank" rel="noopener">$1</a>' , $text );
        // Fettschrift **Text**
        return preg_replace('/\*\*(.+?)\*\*/' , '<strong>$1</strong>' , $text );
    }

}
