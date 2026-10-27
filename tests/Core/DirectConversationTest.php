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
     * Wie Hermine message/conversations liefert: members ohne den eigenen Account, user_count mit ihm
     */
    public function testListWithoutTheOwnAccount(): void
    {
        $this->assertTrue( StashcatMediator::isDirectConversation( self::conversation( [ 42 ] , 2 ) , '1' , '42' ) );
        $this->assertTrue( StashcatMediator::isDirectConversation( self::conversation( [ 42 ] ) , '1' , '42' ) );
        // Gruppe mit drei Personen, in der Liste nur zwei sichtbar
        $this->assertFalse( StashcatMediator::isDirectConversation( self::conversation( [ 7 , 42 ] , 3 ) , '1' , '42' ) );
        $this->assertFalse( StashcatMediator::isDirectConversation( self::conversation( [ 42 ] , 3 ) , '1' , '42' ) );
        $this->assertFalse( StashcatMediator::isDirectConversation( self::conversation( [ 7 ] , 2 ) , '1' , '42' ) );
    }

    /**
     * @param int[] $memberIds
     */
    private static function conversation( array $memberIds , ?int $userCount = null ): Conversation
    {
        return new Conversation( [ 'id' => 99 , 'members' => array_map( fn( int $id ) => [ 'id' => $id ] , $memberIds ) ] + ( $userCount === null ? [] : [ 'user_count' => $userCount ] ) );
    }
}
