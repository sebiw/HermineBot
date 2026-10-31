<?php

namespace App\Core;

use Symfony\Component\Filesystem\Filesystem;

/**
 * Sperre über flock: wird beim Prozessende automatisch frei, auch nach einem Absturz
 */
final class FileLock
{
    /**
     * @param resource $handle
     */
    private function __construct( private $handle )
    {
    }

    /**
     * @return self|null null = gerade gesperrt
     * @throws \RuntimeException wenn die Datei nicht zu öffnen ist
     */
    public static function acquire( string $file ): ?self
    {
        ( new Filesystem() )->mkdir( dirname( $file ) );
        if( ( $handle = fopen( $file , 'c' ) ) === false ){
            throw new \RuntimeException( sprintf( 'Lock file could not be opened: %s' , $file ) );
        }
        if( !flock( $handle , LOCK_EX | LOCK_NB ) ){
            fclose( $handle );
            return null;
        }
        return new self( $handle );
    }

    public static function isLocked( string $file ): bool
    {
        if( !is_file( $file ) ){
            return false;
        }
        $lock = self::acquire( $file );
        $lock?->release();
        return $lock === null;
    }

    public function release(): void
    {
        flock( $this->handle , LOCK_UN );
        fclose( $this->handle );
    }
}
