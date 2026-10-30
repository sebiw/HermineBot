<?php

namespace App\EventListener;

use Doctrine\DBAL\Connection;
use Psr\Log\LogLevel;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Protokolliert unerwartete Fehler (500) der Weboberfläche und der API ins Log (Tabelle log, Ansicht unter /events/log) –
 * produktiv schreibt Monolog nur nach stderr, und das landet auf dem Webspace nirgends.
 * Mit Seite, Benutzer, Klasse und Ort im Code, von POST-Feldern nur die Namen (keine Werte – Passwörter, Texte).
 * Direkt per DBAL (kein flush halb geänderter Entities); geht das nicht (z. B. Datenbank weg), eine Zeile in
 * var/log/errors.log.
 */
#[AsEventListener( event: KernelEvents::EXCEPTION , priority: -10 )]
final class RequestErrorLogListener
{
    public function __construct(
        private readonly Connection $connection ,
        private readonly Security $security ,
        #[Autowire('%kernel.logs_dir%')] private readonly string $logsDir
    )
    {
    }

    public function __invoke( ExceptionEvent $event ): void
    {
        $exception = $event->getThrowable();
        if( $exception instanceof HttpExceptionInterface && $exception->getStatusCode() < 500 ){
            return;
        }
        $message = sprintf( 'Unerwarteter Fehler bei %s: %s' , $this->userLabel() , $exception->getMessage() );
        $context = self::requestContext( $event->getRequest() ) + [
            'exception' => $exception::class,
            'at' => sprintf( '%s:%d' , str_replace( dirname( __DIR__ , 2 ) . '/' , '' , $exception->getFile() ) , $exception->getLine() ),
        ];
        $this->write( $message , $context );
    }

    /**
     * @return array<string, mixed>
     */
    public static function requestContext( Request $request ): array
    {
        $fields = $request->isMethod( 'POST' ) ? array_values( array_filter( array_map( 'strval' , array_keys( $request->request->all() ) ) , fn( string $name ) => $name !== '_token' && $name !== '_csrf_token' ) ) : [];
        return [
            'route' => $request->attributes->get( '_route' ),
            'method' => $request->getMethod(),
            'path' => $request->getPathInfo(),
        ] + ( $fields === [] ? [] : [ 'fields' => $fields ] );
    }

    private function userLabel(): string
    {
        try {
            $user = $this->security->getUser();
        } catch( \Throwable ){
            $user = null;
        }
        return $user === null ? 'ohne Anmeldung' : '„' . $user->getUserIdentifier() . '“';
    }

    /**
     * @param array<string, mixed> $context
     */
    private function write( string $message , array $context ): void
    {
        $json = (string) json_encode( $context , JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE );
        $now = ( new \DateTimeImmutable() )->format( 'Y-m-d H:i:s' );
        try {
            $this->connection->insert( 'log' , [
                'channel' => 'app',
                'level' => LogLevel::ERROR,
                'message' => mb_substr( $message , 0 , 2000 ),
                'context' => $json,
                'created_date_time' => $now,
            ] );
        } catch( \Throwable $failure ){
            // Logging must never break the response – last resort a file next to the other logs
            @file_put_contents( $this->logsDir . '/errors.log' , sprintf( "[%s] %s %s (Log-Tabelle nicht erreichbar: %s)\n" , $now , $message , $json , $failure->getMessage() ) , FILE_APPEND | LOCK_EX );
        }
    }
}
