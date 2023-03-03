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

}