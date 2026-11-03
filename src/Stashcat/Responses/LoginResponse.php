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

    /**
     * @return mixed
     */
    public function getClientKey(){
        return $this->getResults('payload' , 'client_key' );
    }

    /**
     * @return mixed
     */
    public function getUserPublicKey(){
        return $this->getResults('payload' , 'userinfo' , 'public_key' );
    }

    /**
     * @return mixed
     */
    public function getUserId(){
        return $this->getResults('payload' , 'userinfo' , 'id' );
    }

}