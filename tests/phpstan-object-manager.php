<?php

// Für phpstan-doctrine: Mit den Metadaten weiß PHPStan, dass Doctrine die Felder der Entities liest und schreibt.
// Baut keine Datenbankverbindung auf.

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

require_once __DIR__ . '/phpstan-bootstrap.php';
require __DIR__ . '/../vendor/autoload.php';

( new Dotenv() )->bootEnv( __DIR__ . '/../.env' );
$kernel = new Kernel( 'test' , false );
$kernel->boot();

/** @var \Doctrine\Persistence\ManagerRegistry $doctrine */
$doctrine = $kernel->getContainer()->get( 'doctrine' );
return $doctrine->getManager();
