<?php

namespace App\Stashcat\Responses;

class PrivateKeyResponse extends DefaultResponse {

    /**
     * @return string
     * @throws \Exception
     */
    public function getPrivateKeyRaw() : string{
        return $this->getResults('payload' , 'keys' , 'private_key' );
    }

    /**
     * @return mixed
     */
    public function getPrivateKey() : string {
        $private_key = json_decode( $this->getPrivateKeyRaw() );
        return $private_key->private;
    }

}