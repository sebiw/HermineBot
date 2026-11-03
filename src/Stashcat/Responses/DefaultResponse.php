<?php

namespace App\Stashcat\Responses;

use App\Core\ArrayDataCollection;

class DefaultResponse extends ArrayDataCollection implements \JsonSerializable {

    /**
     * @return mixed Wert aus „status“ der Antwort
     */
    public function _getStatusValue(){
        return $this->getResults('status' , 'value');
    }

    /**
     * @return mixed Wert aus „status“ der Antwort
     */
    public function _getShortMessage(){
        return $this->getResults('status' , 'short_message');
    }

    /**
     * @return mixed Wert aus „status“ der Antwort
     */
    public function _getMessage(){
        return $this->getResults('status' , 'message');
    }

    public function jsonSerialize() : mixed
    {
        return $this->getResults();
    }
}