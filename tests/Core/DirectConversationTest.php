<?php

namespace App\Tests\Core;

use App\Core\StashcatMediator;
use App\Stashcat\Entities\Conversation;
use PHPUnit\Framework\TestCase;

/**
 * Erkennen der 1:1-Unterhaltung für /api/direct-message (erfundene IDs, ohne Hermine)
 */
class DirectConversationTest extends TestCase
{
    public function testOnlyTheOwnAccountAndThePerson(): void
    {
        $direct = self::conversation( [ 1 , 42 ] );

        $this->assertTrue( StashcatMediator::isDirectConversation( $direct , '1' , '42' ) );
        $this->assertTrue( StashcatMediator::isDirectConversation( self::conversation( [ 42 , 1 ] ) , '1' , '42' ) );
        $this->assertFalse( StashcatMediator::isDirectConversation( self::conversation( [ 1 , 42 , 7 ] ) , '1' , '42' ) );
        $this->assertFalse( StashcatMediator::isDirectConversation( self::conversation( [ 1 , 7 ] ) , '1' , '42' ) );
        // Never a conversation of the own account with itself
        $this->assertFalse( StashcatMediator::isDirectConversation( self::conversation( [ 1 ] ) , '1' , '1' ) );
        $this->assertFalse( StashcatMediator::isDirectConversation( $direct , null , '42' ) );
    }

    /**
     * @param int[] $memberIds
     */
    private static function conversation( array $memberIds ): Conversation
    {
        return new Conversation( [ 'id' => 99 , 'members' => array_map( fn( int $id ) => [ 'id' => $id ] , $memberIds ) ] );
    }
}
