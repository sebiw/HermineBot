<?php

namespace App\Stashcat\Responses;

use \App\Stashcat\Entities\Message;

class MessageContentResponse extends DefaultResponse {

    /**
     * @return array|Message[]
     * @throws \Exception
     */
    public function getMessages(){
        $messages = [];
        foreach( $this->getResults('payload' , 'messages' ) AS $message ) {
            $messages[] = new Message( $message );
        }
        return $messages;
    }

}