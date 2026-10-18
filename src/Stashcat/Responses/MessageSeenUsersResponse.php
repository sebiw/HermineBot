<?php

namespace App\Stashcat\Responses;

use App\Stashcat\Entities\MessageSeenUser;

class MessageSeenUsersResponse extends DefaultResponse {

    /**
     * @return array|MessageSeenUser[]
     * @throws \Exception
     */
    public function getSeenUsers(): array
    {
        $seenUsers = [];
        foreach( $this->getResults('payload' , 'message_seen_users' ) AS $seenUser ){
            $seenUsers[] = new MessageSeenUser( $seenUser );
        }
        return $seenUsers;
    }

}
