<?php

namespace App\Stashcat\Responses;

/**
 * Privater Signaturschlüssel (security/get_private_key mit type=signing, format=jwk)
 */
class SigningKeyResponse extends DefaultResponse {

    /**
     * @return string
     * @throws \Exception
     */
    public function getPrivateKeyRaw() : string {
        return $this->getResults('payload' , 'keys' , 'private_key' );
    }

    /**
     * Verschlüsselter JWK: [ 'ciphertext' , 'iv' , 'encryptedKEK' , 'encryption_func' ] (jeweils base64)
     * @return array
     * @throws \Exception
     */
    public function getEncryptedPrivateKey() : array {
        $privateKey = json_decode( $this->getPrivateKeyRaw() , true );
        foreach( [ 'ciphertext' , 'iv' , 'encryptedKEK' , 'encryption_func' ] AS $key ){
            if( !isset( $privateKey[ $key ] ) ){
                throw new \Exception( sprintf( 'Signing key incomplete, %s missing!' , $key ) );
            }
        }
        return $privateKey;
    }

}
