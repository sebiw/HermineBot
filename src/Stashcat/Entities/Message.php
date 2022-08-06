<?php

namespace App\Stashcat\Entities;

class Message extends DefaultEntity {

    private bool $encrypted = true;

    /**
     * @return int
     * @throws \Exception
     */
    public function getId(): int
    {
        return intval( $this->getResults('id'));
    }

    public function getText(){
        return $this->getResults('text');
    }

    public function getIv(){
        return $this->getResults('iv');
    }

    public function getHash(){
        return $this->getResults('hash');
    }

    public function getTime(){
        return $this->getResults('time');
    }

    public function getTimeAsDateTime(){
        return (new \DateTime())->setTimestamp( $this->getTime() );
    }

    public function getSenderFirstName(){
        return $this->getResults('sender' , 'first_name');
    }

    public function getSenderLastName(){
        return $this->getResults('sender' , 'last_name');
    }

    /**
     * @return int
     * @throws \Exception
     */
    public function getLikes(): int
    {
        return intval( $this->getResults('likes') );
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function isLiked(): bool
    {
        return boolval( $this->getResults('liked') );
    }

    /**
     * @return bool
     */
    public function isEncrypted(): bool
    {
        return $this->encrypted;
    }

    /**
     * @param string $decryptedMessage
     * @return Message
     * @throws \Exception
     */
    public function toDecrypted( string $decryptedMessage ){
        $clone = clone $this;
        $clone->setResult( $decryptedMessage , 'text' );
        return $clone;
    }

}