<?php
/**
 * Rendert Seiten des HermineBots lokal, nur per GET, als Administrator (oder ohne Anmeldung für die Login-Seite).
 * Seiten, die sich bei Hermine anmelden oder etwas senden, sind gesperrt; die Debug-Leiste ist aus.
 *
 * Aufruf: php8.5 render.php <ausgabeordner> <admin|gast> <pfad> [<pfad> ...]
 * Pfad @first:/events/{id} setzt die kleinste vorhandene ID der Tabelle zum Pfad ein (events, user).
 */

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Filesystem\Filesystem;

$projectDir = dirname( __DIR__ , 4 );
[ , $outDir , $view ] = $argv + [ null , null , null ];
$paths = array_slice( $argv , 3 );
if( $outDir === null || !in_array( $view , [ 'admin' , 'gast' ] , true ) || $paths === [] ){
    fwrite( STDERR , "Aufruf: render.php <ausgabeordner> <admin|gast> <pfad> [<pfad> ...]\n" );
    exit( 2 );
}
// Melden sich bei Hermine an oder senden: nie rendern (/events/execute zeigt per GET nur einen Knopf und ist erlaubt)
const BLOCKED = [ '#^/api/#' , '#^/events/conversations#' , '#/send$#' , '#^/logout#' ];

define( 'BASE_PATH' , $projectDir );
// Symfony startet die Test-Sitzung erst, wenn noch nichts ausgegeben wurde: Ausgaben sammeln, am Ende schreiben
ob_start();
require $projectDir . '/vendor/autoload.php';
( new Dotenv() )->bootEnv( $projectDir . '/.env' );
if( ( $_SERVER['APP_ENV'] ?? '' ) !== 'dev' ){
    fwrite( STDERR , "Abbruch: nur in der lokalen Entwicklungsumgebung.\n" );
    exit( 2 );
}

final class NoToolbarPass implements CompilerPassInterface
{
    public function process( ContainerBuilder $container ): void
    {
        if( $container->hasDefinition( 'web_profiler.debug_toolbar' ) ){
            $container->removeDefinition( 'web_profiler.debug_toolbar' );
        }
    }
}

final class RenderKernel extends App\Kernel
{
    public function __construct( private string $root , private string $cache )
    {
        parent::__construct( 'dev' , false );
    }

    public function getProjectDir(): string
    {
        return $this->root;
    }

    public function getCacheDir(): string
    {
        return $this->cache;
    }

    public function getLogDir(): string
    {
        return $this->cache . '/log';
    }

    protected function build( ContainerBuilder $container ): void
    {
        $container->prependExtensionConfig( 'framework' , [ 'test' => true , 'session' => [ 'storage_factory_id' => 'session.storage.factory.mock_file' ] ] );
        $container->setAlias( App\Kernel::class , 'kernel' )->setPublic( true );
        $container->addCompilerPass( new NoToolbarPass() , PassConfig::TYPE_OPTIMIZE );
    }
}

$filesystem = new Filesystem();
$filesystem->mkdir( $outDir );
$cache = sys_get_temp_dir() . '/bot-render-' . getmypid();
$lines = [];
try {
    $kernel = new RenderKernel( $projectDir , $cache );
    $kernel->boot();
    $connection = $kernel->getContainer()->get( 'doctrine' )->getConnection();
    $browser = new KernelBrowser( $kernel );
    $browser->disableReboot();
    if( $view === 'admin' ){
        $admin = null;
        foreach( $kernel->getContainer()->get( 'doctrine' )->getManager()->getRepository( User::class )->findAll() as $user ){
            if( in_array( 'ROLE_ADMIN' , $user->getRoles() , true ) ){
                $admin = $user;
                break;
            }
        }
        if( $admin === null ){
            throw new RuntimeException( 'Kein lokaler Administrator' );
        }
        $browser->loginUser( $admin , 'secured_area' );
    }

    foreach( $paths as $path ){
        if( preg_match( '#^@first:(.*)\{id\}(.*)$#' , $path , $match ) === 1 ){
            $table = str_starts_with( $match[1] , '/events' ) ? 'events' : 'user';
            $id = $connection->fetchOne( sprintf( 'SELECT MIN(id) FROM `%s`' , $table ) );
            if( !$id ){
                $lines[] = sprintf( '%-24s keine Zeile in %s' , $path , $table );
                continue;
            }
            $path = $match[1] . $id . $match[2];
        }
        foreach( BLOCKED as $pattern ){
            if( preg_match( $pattern , $path ) === 1 ){
                $lines[] = sprintf( '%-24s gesperrt (erreicht Hermine oder sendet)' , $path );
                continue 2;
            }
        }
        $browser->request( 'GET' , $path );
        $status = $browser->getResponse()->getStatusCode();
        $file = $view . '__' . ( trim( preg_replace( '/[^a-zA-Z0-9]+/' , '_' , $path ) , '_' ) ?: 'start' ) . '.html';
        file_put_contents( rtrim( $outDir , '/' ) . '/' . $file , (string) $browser->getResponse()->getContent() );
        $lines[] = sprintf( '%-24s %3d -> %s' , $path , $status , $file );
    }

    // CSS und Skripte, damit die Seiten im Browser wie echt aussehen
    $link = rtrim( $outDir , '/' ) . '/assets';
    if( !file_exists( $link ) ){
        symlink( $projectDir . '/public/assets' , $link );
    }
} finally {
    ob_end_clean();
    $filesystem->remove( $cache );
    echo implode( "\n" , $lines ) , "\n";
}
