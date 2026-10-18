<?php

namespace App\Stashcat\Responses;

use App\Stashcat\Entities\Conversation;

class CreateConversationResponse extends DefaultResponse {

    /**
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->resultKeyExist('payload' , 'conversation' );
    }

    /**
     * @return string
     */
    public function getErrorMessage(): string
    {
        return $this->resultKeyExist('status' , 'message') ? (string) $this->_getMessage() : 'unknown error';
    }

    /**
     * @return Conversation
     * @throws \Exception
     */
    public function getConversation(): Conversation
    {
        return new Conversation( $this->getResults('payload' , 'conversation' ) );
    }

}
