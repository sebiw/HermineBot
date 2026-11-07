<?php

namespace App\Stashcat;

use App\Core\Crypt;
use App\Stashcat\Entities\Message;
use App\Stashcat\Entities\User;
use App\Stashcat\Responses\PrivateKeyResponse;
use App\Stashcat\Responses\SigningKeyResponse;

class CryptoBox {

    // Länge des Schlüssels einer Unterhaltung in Byte (Web-Client: CHAT_ACCESS_KEY_LENGTH_BY)
    const CHAT_ACCESS_KEY_LENGTH = 32;

    private ?\OpenSSLAsymmetricKey $privateKey = null;

    private ?\OpenSSLAsymmetricKey $privateSigningKey = null;

    /** @var array<string, string> entschlüsselte Schlüssel je Channel-ID */
    private array $channelKeys = [];

    /** @var array<string, string> entschlüsselte Schlüssel je Unterhaltungs-ID */
    private array $conversationKeys = [];

    private Config $config;


    /**
     * @param Config $config
     */
    public function __construct( Config $config )
    {
        $this->config = $config;
    }

    /**
     * @return Config
     */
    public function getConfig() : Config {
        return $this->config;
    }

    /**
     * @param PrivateKeyResponse $response
     * @throws \Exception
     */
    public function setPrivateKeyFromResponse( PrivateKeyResponse $response ): void {
        $this->privateKey = Crypt::decodePrivateKey( $response->getPrivateKey() , $this->getConfig()->getUserPassphrase() );
    }

    /**
     * Privater Signaturschlüssel: KEK per RSA-OAEP mit dem privaten Hauptschlüssel entschlüsseln, damit den JWK (AES-256-CBC)
     * @param SigningKeyResponse $response
     * @throws \Exception
     */
    public function setPrivateSigningKeyFromResponse( SigningKeyResponse $response ): void {
        if( $this->privateKey === null ){
            throw new \Exception('Private key not available!');
        }
        $encryptedKey = $response->getEncryptedPrivateKey();
        if( strtolower( $encryptedKey['encryption_func'] ) !== 'aes-256-cbc' ){
            throw new \Exception( sprintf( 'Unsupported encryption function %s for signing key!' , $encryptedKey['encryption_func'] ) );
        }
        $kek = Crypt::decodeWithPrivateKey( self::base64( $encryptedKey['encryptedKEK'] , 'KEK of the signing key' ) , $this->privateKey , $this->getConfig()->getRsaOaepPadding() );
        $jwk = openssl_decrypt( self::base64( $encryptedKey['ciphertext'] , 'signing key' ) , 'aes-256-cbc' , $kek , OPENSSL_RAW_DATA , self::base64( $encryptedKey['iv'] , 'IV of the signing key' ) );
        if( $jwk === false ){
            throw new \Exception( sprintf( 'Signing key decryption failed: %s' , implode( ', ' , Crypt::getErrors() ) ) );
        }
        $jwk = json_decode( $jwk , true );
        if( !is_array( $jwk ) ){
            throw new \Exception('Signing key is no valid JWK!');
        }
        $this->privateSigningKey = Crypt::rsaPrivateKeyFromJwk( $jwk );
    }

    /**
     * @return bool
     */
    public function hasPrivateSigningKey(): bool
    {
        return $this->privateSigningKey !== null;
    }

    /**
     * @param string $encodedChannelKey
     * @param string $channelId
     * @throws \Exception
     */
    public function setChannelEncryptionChannel( string $encodedChannelKey , string $channelId ): void {
        $channelKeyBase64Decoded = self::base64( $encodedChannelKey , 'channel key' );
        $this->channelKeys[ $channelId ] = Crypt::decodeWithPrivateKey( $channelKeyBase64Decoded , $this->requirePrivateKey() ,  $this->getConfig()->getRsaOaepPadding() );
    }


    /**
     * @param string $encodedConversationKey
     * @param string $conversationId
     * @throws \Exception
     */
    public function setConversationEncryptionConversation( string $encodedConversationKey , string $conversationId ): void {
        $conversationKeyBase64Decoded = self::base64( $encodedConversationKey , 'conversation key' );
        $this->conversationKeys[ $conversationId ] = Crypt::decodeWithPrivateKey( $conversationKeyBase64Decoded , $this->requirePrivateKey() ,  $this->getConfig()->getRsaOaepPadding() );
    }

    /**
     * Unverschlüsselten Schlüssel einer Unterhaltung direkt setzen (z. B. nach dem Anlegen)
     * @param string $conversationKey
     * @param string $conversationId
     */
    public function setConversationKey( string $conversationKey , string $conversationId ): void {
        $this->conversationKeys[ $conversationId ] = $conversationKey;
    }

    /**
     * Neuer Schlüssel für eine Unterhaltung (AES-256)
     * @return string
     */
    public function getRandomConversationKey(): string
    {
        return random_bytes( self::CHAT_ACCESS_KEY_LENGTH );
    }

    /**
     * Signierter Text wie im Web-Client (getChatKeySignedContent):
     * key $ unique_identifier [$ expiry] [$ Mitglieder-IDs eindeutig, numerisch aufsteigend]
     * @param string $encryptedKey base64
     * @param string|null $uniqueIdentifier
     * @param array<int|string> $memberIds
     * @param int|null $expiry
     * @return string
     */
    public function getChatKeySignedContent( string $encryptedKey , ?string $uniqueIdentifier , array $memberIds , ?int $expiry ): string
    {
        $content = $encryptedKey . '$' . ( $uniqueIdentifier ?? '' );
        if( $expiry !== null ){
            $content .= '$' . $expiry;
        }
        if( !empty( $memberIds ) ){
            $memberIds = array_values( array_unique( array_map( 'intval' , $memberIds ) ) );
            sort( $memberIds , SORT_NUMERIC );
            $content .= '$' . implode( '$' , $memberIds );
        }
        return $content;
    }

    /**
     * Schlüssel einer Unterhaltung für ein Mitglied verschlüsseln und signieren
     * @param string $conversationKey unverschlüsselt
     * @param string $userId
     * @param string $publicKeyPem öffentlicher Hauptschlüssel des Mitglieds
     * @param string $uniqueIdentifier
     * @param array<int|string> $memberIds alle Mitglieder inkl. sich selbst
     * @param int|null $expiry Unix-Zeit, bei sich selbst null
     * @param bool $userVerified
     * @return array{id: int, key: string, signature: string, expiry: int|null, userVerified: bool}
     * @throws \Exception
     */
    public function getConversationMemberKey( string $conversationKey , string $userId , string $publicKeyPem , string $uniqueIdentifier , array $memberIds , ?int $expiry , bool $userVerified ): array
    {
        if( $this->privateSigningKey === null ){
            throw new \Exception('Private signing key not available!');
        }
        $encryptedKey = base64_encode( Crypt::encodeWithPublicKey( $conversationKey , $publicKeyPem , $this->getConfig()->getRsaOaepPadding() ) );
        $signature = Crypt::signRsaSha256( $this->getChatKeySignedContent( $encryptedKey , $uniqueIdentifier , $memberIds , $expiry ) , $this->privateSigningKey );
        return [
            'id' => (int) $userId,
            'key' => $encryptedKey,
            'signature' => $signature,
            'expiry' => $expiry,
            'userVerified' => $userVerified
        ];
    }

    /**
     * Schlüssel einer Person prüfen (wie verifyUsersPublicKey im Web-Client):
     * Hauptschlüssel signiert mit ihrem Signaturschlüssel, Signaturschlüssel signiert vom Server (nur wenn serverPublicKey gesetzt ist)
     * @param User $user mit Schlüsseln (users/info mit withkey)
     * @return bool
     */
    public function isUserKeyVerified( User $user ): bool
    {
        try {
            $publicKeyPem = $user->getPublicKey();
            $signingKeyJwk = $user->getPublicSigningKey();
            $publicKeySignature = $user->getPublicKeySignature();
            if( $publicKeyPem === null || $signingKeyJwk === null || empty( $publicKeySignature ) ){
                return false;
            }
            $publicKey = openssl_pkey_get_public( $publicKeyPem );
            Crypt::getErrors(); // Fehlerpuffer leeren
            if( $publicKey === false ){
                return false;
            }
            $signingKey = Crypt::rsaPublicKeyFromJwk( $signingKeyJwk );

            if( !Crypt::verifyRsaSha256( Crypt::rsaKeyFingerprint( $publicKey ) . '$' . $user->getId() , $publicKeySignature , $signingKey ) ){
                return false;
            }

            if( $this->getConfig()->getServerPublicKey() !== '' ){
                $serverKey = openssl_pkey_get_public( $this->getConfig()->getServerPublicKey() );
                Crypt::getErrors(); // Fehlerpuffer leeren
                $caSignature = $user->getPublicKeyCaSignature();
                if( $serverKey === false || empty( $caSignature ) ){
                    return false;
                }
                return Crypt::verifyRsaSha256( Crypt::rsaKeyFingerprint( $signingKey ) . '$' . $user->getId() , $caSignature , $serverKey );
            }
            return true;
        } catch ( \Exception $e ){
            return false;
        }
    }

    /**
     * @return string
     */
    public function getRandomIv(): string {
        $ivLength = openssl_cipher_iv_length( $this->getConfig()->getCipherAlgo() );
        if( $ivLength === false ){
            throw new \RuntimeException('Unknown cipher algorithm');
        }
        return openssl_random_pseudo_bytes( $ivLength );
    }

    /**
     * @param string $messageToSend
     * @param string $channelId
     * @param string $iv
     * @return string
     * @throws \Exception
     */
    public function getChannelMessageEncrypted( string $messageToSend , string $channelId , string &$iv ) : string {
        $iv = $this->getRandomIv();
        $result = Crypt::textEncoder( $messageToSend );
        // Pad own content...
        $result = Crypt::padString( $result , $this->getConfig()->getCipherAlgoPaddingBlockSize() );

        if( !isset( $this->channelKeys[ $channelId ] ) ){
            throw new \RuntimeException(sprintf('Channel-Key %s not available!' , $channelId ));
        }

        // Encrypt
        $ciphertext = Crypt::encodeWithChannelKey( $result ,  $this->getConfig()->getCipherAlgo() , $this->channelKeys[ $channelId ] ,  $this->getConfig()->getAesPadding() , $iv );
        // Encode IV
        $iv = Crypt::bytesToHex( $iv );
        // Encode encrypted
        return Crypt::bytesToHex( $ciphertext );
    }

    /**
     * @param string $messageToSend
     * @param string $conversationId
     * @param string $iv
     * @return string
     * @throws \Exception
     */
    public function getConversationMessageEncrypted( string $messageToSend , string $conversationId , string &$iv ) : string {
        $iv = $this->getRandomIv();
        $result = Crypt::textEncoder( $messageToSend );
        // Pad own content...
        $result = Crypt::padString( $result , $this->getConfig()->getCipherAlgoPaddingBlockSize() );
        // Encrypt

        if( !isset( $this->conversationKeys[ $conversationId ] ) ){
            throw new \RuntimeException(sprintf('Conversation-Key %s not available!' , $conversationId ));
        }

        $ciphertext = Crypt::encodeWithConversationKey( $result ,  $this->getConfig()->getCipherAlgo() , $this->conversationKeys[ $conversationId ] ,  $this->getConfig()->getAesPadding() , $iv );
        // Encode IV
        $iv = Crypt::bytesToHex( $iv );
        // Encode encrypted
        return Crypt::bytesToHex( $ciphertext );
    }

    /**
     * @param Message $message
     * @param string $channelId
     * @return string
     * @throws \Exception
     */
    public function getChannelMessageDecrypted( Message $message , string $channelId ): string {
        if( !isset( $this->channelKeys[ $channelId ] ) ){
            throw new \RuntimeException(sprintf('Channel-Key %s not available!' , $channelId ));
        }
        $messageText = Crypt::binaryStringToString( Crypt::hexToBytes( $message->getText() ) );
        $messageIv = Crypt::binaryStringToString( Crypt::hexToBytes( $message->getIv() ) );
        $decrypted = Crypt::decodeWithPassphrase( $messageText , $this->getConfig()->getCipherAlgo() , $this->channelKeys[ $channelId ] , $this->getConfig()->getAesPadding() , $messageIv );
        return Crypt::unPadString( $decrypted );
    }

    /**
     * @param Message $message
     * @param string $conversationId
     * @return string
     * @throws \Exception
     */
    public function getConversationMessageDecrypted( Message $message , string $conversationId ): string {
        if( !isset( $this->conversationKeys[ $conversationId ] ) ){
            throw new \RuntimeException(sprintf('Conversation-Key %s not available!' , $conversationId ));
        }
        $messageText = Crypt::binaryStringToString( Crypt::hexToBytes( $message->getText() ) );
        $messageIv = Crypt::binaryStringToString( Crypt::hexToBytes( $message->getIv() ) );
        $decrypted = Crypt::decodeWithPassphrase( $messageText , $this->getConfig()->getCipherAlgo() , $this->conversationKeys[ $conversationId ] , $this->getConfig()->getAesPadding() , $messageIv );
        return Crypt::unPadString( $decrypted );
    }

    /**
     * @throws \Exception
     */
    private function requirePrivateKey(): \OpenSSLAsymmetricKey
    {
        return $this->privateKey ?? throw new \Exception('Private key not available!');
    }

    /**
     * @throws \Exception
     */
    private static function base64( string $data , string $what ): string
    {
        $decoded = base64_decode( $data , true );
        if( $decoded === false ){
            throw new \Exception( sprintf( 'Invalid base64 in %s!' , $what ) );
        }
        return $decoded;
    }

}
