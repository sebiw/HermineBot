<?php

namespace App\Stashcat\Responses;

class AuthCheckResponse extends DefaultResponse {

    /**
     * @return mixed true = Anmeldung gilt noch
     */
    public function success(){
        return $this->getResults('payload' , 'success');
    }



}