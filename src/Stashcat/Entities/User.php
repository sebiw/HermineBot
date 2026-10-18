<?php

namespace App\Stashcat\Entities;

use App\Core\THWStashcatConventions;

class User extends DefaultEntity {

    public function getFirstName(){
        return $this->getResults('first_name');
    }

    public function getLastName(){
        return $this->getResults('last_name');
    }

    public function getId(){
        return $this->getResults('id');
    }

    /**
     * Öffentlicher Hauptschlüssel (PEM), nur bei users/info mit withkey
     * @return string|null
     */
    public function getPublicKey(): ?string
    {
        return $this->resultKeyExist('public_key') ? $this->getResults('public_key') : null;
    }

    /**
     * Öffentlicher Signaturschlüssel als JWK
     * @return array|null
     */
    public function getPublicSigningKey(): ?array
    {
        if( !$this->resultKeyExist('public_signing_key') ){
            return null;
        }
        $key = $this->getResults('public_signing_key');
        if( is_string( $key ) ){
            $key = json_decode( $key , true );
        }
        return is_array( $key ) ? $key : null;
    }

    /**
     * Signatur des Hauptschlüssels mit dem eigenen Signaturschlüssel (hex)
     * @return string|null
     */
    public function getPublicKeySignature(): ?string
    {
        return $this->resultKeyExist('public_key_signature') ? $this->getResults('public_key_signature') : null;
    }

    /**
     * Signatur des Signaturschlüssels durch den Server (hex)
     * @return string|null
     */
    public function getPublicKeyCaSignature(): ?string
    {
        return $this->resultKeyExist('public_key_ca_signature') ? $this->getResults('public_key_ca_signature') : null;
    }

    /**
     * @return array [ 'first_name' => $firstName , 'last_name' => $lastName , 'department' => $department ]
     */
    public function getParsedName(): array
    {
        $nameParts = THWStashcatConventions::extractNameParts( $this->getLastName() );
        $lastName = $nameParts['last_name'];
        $firstName = $this->getFirstName();
        $department = $nameParts['department'];
        return [ 'first_name' => $firstName , 'last_name' => $lastName , 'department' => $department ];
    }

    /**
     * @return string
     */
    public function getCompleteName(): string
    {
        return $this->getFirstName() . ' ' . $this->getLastName();
    }

}