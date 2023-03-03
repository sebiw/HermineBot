<?php

namespace App\Stashcat\Responses;

use App\Stashcat\Entities\Company;
use App\Stashcat\Entities\Conversation;
use App\Stashcat\Entities\Group;

class MessageConversationsResponse extends DefaultResponse {


    /**
     * @return array|Conversation[]
     * @throws \Exception
     */
    public function getConversations(): array
    {
        $groups = [];
        foreach( $this->getResults('payload' , 'conversations' ) AS $conversation ){
            $groups[ $conversation['id'] ] = new Conversation( $conversation );
        }
        return $groups;
    }

}