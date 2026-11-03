<?php

namespace App\Stashcat\Entities;

class Company extends DefaultEntity {

    /**
     * @return mixed Wert aus der Antwort von Hermine
     */
    public function getName(){
        return $this->getResults('name');
    }

    /**
     * @return mixed Wert aus der Antwort von Hermine
     */
    public function getId(){
        return $this->getResults('id');
    }

    /**
     * @return mixed Wert aus der Antwort von Hermine
     */
    public function getCreatedUser(){
        return $this->getResults('users' , 'created');
    }

    /**
     * @return mixed Wert aus der Antwort von Hermine
     */
    public function getActiveUser(){
        return $this->getResults('users' , 'active');
    }

}