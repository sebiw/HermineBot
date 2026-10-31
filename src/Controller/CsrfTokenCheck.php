<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;

/**
 * Prüfung des Formular-Tokens für die Controller
 */
trait CsrfTokenCheck
{
    private function denyAccessUnlessCsrfTokenValid( string $tokenId , Request $request ): void
    {
        if( !$this->isCsrfTokenValid( $tokenId , $request->request->getString('_token') ) ){
            throw $this->createAccessDeniedException('Ungültiges Formular-Token – bitte Seite neu laden.');
        }
    }
}
