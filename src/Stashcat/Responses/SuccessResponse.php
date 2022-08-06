<?php

namespace App\Stashcat\Responses;

class SuccessResponse extends DefaultResponse {

    public function isSuccessful() : bool {
        return boolval( $this->getResults('payload' , 'success') );
    }

}