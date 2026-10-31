<?php

namespace App\Tests\Core;

use App\Core\FileLock;
use PHPUnit\Framework\TestCase;

class FileLockTest extends TestCase
{
    public function testSecondAcquireFailsUntilReleased(): void
    {
        $file = tempnam( sys_get_temp_dir() , 'lock' );
        $lock = FileLock::acquire( $file );
        $this->assertNotNull( $lock );
        $this->assertNull( FileLock::acquire( $file ) );
        $this->assertTrue( FileLock::isLocked( $file ) );

        $lock->release();
        $this->assertFalse( FileLock::isLocked( $file ) );
        unlink( $file );
    }

    public function testMissingFileIsNotLocked(): void
    {
        $this->assertFalse( FileLock::isLocked( sys_get_temp_dir() . '/gibt-es-nicht-' . uniqid() ) );
    }
}
