<?php

namespace App\Stashcat\Responses;

use App\Core\ArrayDataCollection;

class DefaultResponse extends ArrayDataCollection implements \JsonSerializable {

    public function _getStatusValue(){
        return $this->getResults('status' , 'value');
    }

    public function _getShortMessage(){
        return $this->getResults('status' , 'short_message');
    }

    public function _getMessage(){
        return $this->getResults('status' , 'message');
    }

    public function jsonSerialize() : mixed
    {
        return $this->getResults();
    }
}