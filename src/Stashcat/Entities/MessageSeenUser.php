<?php

namespace App\Stashcat\Entities;

/**
 * Eintrag aus "wer hat die Nachricht gesehen" – laut Erfahrung nicht zuverlässig, nicht für Auswertungen nutzen
 */
class MessageSeenUser extends DefaultEntity {

    /**
     * @return mixed Wert aus der Antwort von Hermine
     */
    public function getUserId(){
        return $this->getResults('user_id');
    }

    /**
     * @return mixed Wert aus der Antwort von Hermine
     */
    public function getFirstName(){
        return $this->getResults('first_name');
    }

    /**
     * @return mixed Wert aus der Antwort von Hermine
     */
    public function getLastName(){
        return $this->getResults('last_name');
    }

    /**
     * @return \DateTime
     */
    public function getTimeAsDateTime(): \DateTime
    {
        return ( new \DateTime() )->setTimestamp( intval( $this->getResults('time') ) );
    }

}
