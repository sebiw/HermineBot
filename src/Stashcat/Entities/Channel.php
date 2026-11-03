<?php

namespace App\Stashcat\Entities;

class Channel extends DefaultEntity {

    /**
     * @return mixed Wert aus der Antwort von Hermine
     */
    public function getName(){
        return $this->getResults('name');
    }

    /**
     * @return mixed Wert aus der Antwort von Hermine
     */
    public function getDescription(){
        return $this->getResults('description');
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
    public function getEncryption(){
        return $this->getResults('encryption');
    }

    /**
     * @return mixed Wert aus der Antwort von Hermine
     */
    public function getKey(){
        return $this->getResults('key');
    }

    /**
     * @return mixed Wert aus der Antwort von Hermine
     */
    public function getUserCount(){
        return $this->getResults('user_count');
    }

}