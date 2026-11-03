<?php

namespace App\Tests\Core;

use App\Core\MessageDecorator;
use PHPUnit\Framework\TestCase;

/**
 * Priorität von Aufgaben aus der Nextcloud (RFC 5545): 0 heißt „nicht festgelegt“, nicht „hoch“
 */
class IcalPriorityTest extends TestCase
{
    public function testNullIstKeinePrioritaet(): void
    {
        self::assertNull( MessageDecorator::priorityText( 0 ) );
    }

    public function testStufen(): void
    {
        self::assertSame( '🟥 hoch (1)' , MessageDecorator::priorityText( 1 ) );
        self::assertSame( '🟨 mittel (5)' , MessageDecorator::priorityText( 5 ) );
        self::assertSame( '🟦 niedrig (9)' , MessageDecorator::priorityText( 9 ) );
    }
}
