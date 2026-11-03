<?php

namespace App\Stashcat\Responses;

use App\Stashcat\Entities\Message;

class SendMessageResponse extends MessageContentResponse {

    public function getMessages(){
        return [ $this->getMessage() ];
    }

    public function getMessage(): Message {
        return new Message( $this->getResults('payload' , 'message' ) );
    }

}