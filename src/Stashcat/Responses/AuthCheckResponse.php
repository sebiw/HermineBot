<?php

namespace App\Stashcat\Responses;

class AuthCheckResponse extends DefaultResponse {

    public function success(){
        return $this->getResults('payload' , 'success');
    }



}