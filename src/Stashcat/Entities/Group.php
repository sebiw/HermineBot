<?php

namespace App\Stashcat\Entities;

class Group extends DefaultEntity {

    public function getName(){
        return $this->getResults('name');
    }

    public function getId(){
        return $this->getResults('id');
    }

    public function getCompanyId(){
        return $this->getResults('company');
    }

    public function getPossibleUser(){
        return $this->getResults('count');
    }

}