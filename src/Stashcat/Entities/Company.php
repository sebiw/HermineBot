<?php

namespace App\Stashcat\Entities;

class Company extends DefaultEntity {

    public function getName(){
        return $this->getResults('name');
    }

    public function getId(){
        return $this->getResults('id');
    }

    public function getCreatedUser(){
        return $this->getResults('users' , 'created');
    }

    public function getActiveUser(){
        return $this->getResults('users' , 'active');
    }

}