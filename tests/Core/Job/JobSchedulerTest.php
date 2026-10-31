<?php

namespace App\Tests\Core\Job;

use App\Core\Job\JobScheduler;
use PHPUnit\Framework\TestCase;

class JobSchedulerTest extends TestCase
{
    public function testNeverStartedIsDue(): void
    {
        $this->assertTrue( JobScheduler::isDue( null , 1440 , new \DateTimeImmutable( '2026-10-31 12:00:00' ) ) );
    }

    public function testDueWithToleranceOfACronNotStartingExactly(): void
    {
        $last = new \DateTimeImmutable( '2026-10-30 12:00:10' );
        $this->assertFalse( JobScheduler::isDue( $last , 1440 , new \DateTimeImmutable( '2026-10-31 11:59:00' ) ) );
        $this->assertTrue( JobScheduler::isDue( $last , 1440 , new \DateTimeImmutable( '2026-10-31 12:00:00' ) ) );
    }
}
