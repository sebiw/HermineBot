<?php

namespace App\Stashcat\Entities;

class Group extends DefaultEntity {

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
    public function getCompanyId(){
        return $this->getResults('company');
    }

    /**
     * @return mixed Wert aus der Antwort von Hermine
     */
    public function getPossibleUser(){
        return $this->getResults('count');
    }

}