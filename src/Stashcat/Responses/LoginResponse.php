<?php

namespace App\Stashcat\Responses;

class LoginResponse extends DefaultResponse {

    /**
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->_getStatusValue() == 'OK' && $this->resultKeyExist('payload' , 'client_key');
    }

    public function getClientKey(){
        return $this->getResults('payload' , 'client_key' );
    }

    public function getUserSocketId(){
        return $this->getResults('payload' , 'userinfo' , 'socket_id' );
    }

    public function getUserPublicKey(){
        return $this->getResults('payload' , 'userinfo' , 'public_key' );
    }

    public function getUserId(){
        return $this->getResults('payload' , 'userinfo' , 'id' );
    }

}