<?php

namespace App\Stashcat\Entities;

class Conversation extends DefaultEntity {

    public function getId(){
        return $this->getResults('id');
    }

    /**
     * @return array|User[]
     * @throws \Exception
     */
    public function getMembers(): array
    {
        $result = [];
        foreach( $this->getResults('members') AS $member ){
            $result[] = new User( $member );
        }
        return $result;
    }

    public function getKey(){
        return $this->getResults('key');
    }

    public function getKeySignature(){
        return $this->getResults('key_signature');
    }

    public function getUserCount(){
        return $this->getResults('user_count');
    }

    /**
     * Anzahl der Mitglieder inkl. eigenem Account, null = nicht geliefert
     */
    public function getUserCountOrNull(): ?int
    {
        return $this->resultKeyExist('user_count') ? (int) $this->getResults('user_count') : null;
    }

    /**
     * Für den eigenen Account verschlüsselter Schlüssel vorhanden
     */
    public function hasKey(): bool
    {
        return $this->resultKeyExist('key') && $this->getResults('key') !== '';
    }

}