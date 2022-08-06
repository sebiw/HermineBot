<?php

namespace App\Stashcat\Entities;

class Channel extends DefaultEntity {

    public function getName(){
        return $this->getResults('name');
    }

    public function getDescription(){
        return $this->getResults('description');
    }

    public function getId(){
        return $this->getResults('id');
    }

    public function getCompanyId(){
        return $this->getResults('company');
    }

    public function getEncryption(){
        return $this->getResults('encryption');
    }

    public function getKey(){
        return $this->getResults('key');
    }

    public function getUserCount(){
        return $this->getResults('user_count');
    }

}