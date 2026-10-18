<?php

namespace App\Stashcat\Responses;

class MessageSeenUsersCountResponse extends DefaultResponse {

    /**
     * @return int
     * @throws \Exception
     */
    public function getCount(): int
    {
        return intval( $this->getResults('payload' , 'message_seen_count' ) );
    }

}
