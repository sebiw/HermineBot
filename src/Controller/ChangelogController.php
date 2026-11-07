<?php

namespace App\Controller;

use App\Core\ChangelogRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Liefert das Changelog als HTML-Fragment für den Dialog hinter der Versionsanzeige (nur für eingeloggte Nutzer).
 * Ohne Login bewusst 403 statt Umleitung zur Login-Seite, damit der AJAX-Dialog keine komplette Seite lädt.
 */
class ChangelogController extends AbstractController
{

    #[Route('/changelog', name: 'changelog', methods: [ 'GET' ])]
    public function changelog( ChangelogRenderer $renderer , #[Autowire('%kernel.project_dir%')] string $projectDir ): Response
    {
        if( !$this->isGranted('ROLE_USER') ){
            return new Response('<p>Bitte erneut einloggen.</p>' , Response::HTTP_FORBIDDEN );
        }

        $changelogPath = $projectDir . '/CHANGELOG.md';

        $markdown = is_readable( $changelogPath ) ? file_get_contents( $changelogPath ) : false;
        if( $markdown === false ){
            return new Response('<p>Kein Changelog vorhanden.</p>' , Response::HTTP_NOT_FOUND );
        }

        return new Response( $renderer->render( $markdown ) );
    }

}
