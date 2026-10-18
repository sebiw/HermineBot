<?php

namespace App\Stashcat\Responses;

use App\Stashcat\Entities\Conversation;

class ConversationResponse extends DefaultResponse {

    /**
     * @return Conversation
     * @throws \Exception
     */
    public function getConversation(): Conversation
    {
        return new Conversation( $this->getResults('payload' , 'conversation' ) );
    }

}
