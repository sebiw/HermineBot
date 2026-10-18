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

    /**
     * @return mixed|null
     * @throws \Exception
     */
    public function getMetaInfo(){
        if( $this->resultKeyExist('metainfo') ){
            return $this->getResults('metainfo');
        }
        return null;
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
     * Felder, die nicht in jeder Antwort enthalten sind
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected function getOptionalResult( string $key , mixed $default = null ): mixed
    {
        try {
            return $this->getResults( $key ) ?? $default;
        } catch ( \Exception $e ){
            return $default;
        }
    }

    /**
     * Emoji-Reaktionen, nur Anzahl je Emoji (wer reagiert hat, liefert Hermine nicht)
     * @return array [ emoji => Anzahl ]
     */
    public function getReactions(): array
    {
        $reactions = [];
        foreach( $this->getOptionalResult( 'reactions' , [] ) AS $reaction ){
            if( isset( $reaction['emoji'] ) ){
                $reactions[ $reaction['emoji'] ] = intval( $reaction['num_reactions'] ?? 0 );
            }
        }
        return $reactions;
    }

    /**
     * @return mixed ID bzw. Daten der Nachricht, auf die geantwortet wurde
     */
    public function getReplyTo(): mixed
    {
        return $this->getOptionalResult( 'reply_to' );
    }

    /**
     * @return string|null
     */
    public function getThreadId(): ?string
    {
        $threadId = $this->getOptionalResult( 'thread_id' );
        return empty( $threadId ) ? null : strval( $threadId );
    }

    /**
     * @return bool
     */
    public function isSeenByOthers(): bool
    {
        return boolval( $this->getOptionalResult( 'seen_by_others' , false ) );
    }

    /**
     * @return string|null
     */
    public function getSenderId(): ?string
    {
        $sender = $this->getOptionalResult( 'sender' , [] );
        return isset( $sender['id'] ) ? strval( $sender['id'] ) : null;
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