<?php

namespace App\Stashcat;

use App\Core\Crypt;
use App\Stashcat\Entities\Message;
use App\Stashcat\Responses\PrivateKeyResponse;

class CryptoBox {

    private $privateKey = null;

    private $channelKeys = [];

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
    public function setPrivateKeyFromResponse( PrivateKeyResponse $response ){
        $this->privateKey = Crypt::decodePrivateKey( $response->getPrivateKey() , $this->getConfig()->getUserPassphrase() );
    }

    /**
     * @param string $encodedChannelKey
     * @param string $channelId
     * @throws \Exception
     */
    public function setChannelEncryptionChannel( string $encodedChannelKey , string $channelId ){
        $channelKeyBase64Decoded = base64_decode( $encodedChannelKey, true );
        // this.accessKeyStore[E] = this.privateKey.decrypt(f.util.decode64(E), "RSA-OAEP")),
        $this->channelKeys[ $channelId ] = Crypt::decodeWithPrivateKey( $channelKeyBase64Decoded , $this->privateKey ,  $this->getConfig()->getRsaOaepPadding() );
        /*
            getVerification(S, I) {
                return g.L.createHash((S.text ? S.text : "") + this.stashcatService.deviceId + S.microTime + (I || "") + (S.location ? S.location.longitude.toString().replace(".", "") : "") + (S.location ? S.location.latitude.toString().replace(".", "") : ""), "md5")
            }
        */
    }

    /**
     * @return false|string
     */
    public function getRandomIv(){
        $ivLength = openssl_cipher_iv_length( $this->getConfig()->getCipherAlgo() );
        return openssl_random_pseudo_bytes( $ivLength );
    }

    /**
     * @param string $messageToSend
     * @param string $channelId
     * @param string $iv
     * @return string|null
     * @throws \Exception
     */
    public function getChannelMessageEncrypted( string $messageToSend , string $channelId , string &$iv ) : ?string {
        $iv = $this->getRandomIv();
        $result = Crypt::textEncoder( $messageToSend );
        // Pad own content...
        $result = Crypt::padString( $result , $this->getConfig()->getCipherAlgoPaddingBlockSize() );
        // Encrypt
        $ciphertext = Crypt::encodeWithChannelKey( $result ,  $this->getConfig()->getCipherAlgo() , $this->channelKeys[ $channelId ] ,  $this->getConfig()->getAesPadding() , $iv );
        // Encode IV
        $iv = Crypt::bytesToHex( $iv );
        // Encode encrypted
        return Crypt::bytesToHex( $ciphertext );
    }

    /**
     * @param array $message
     * @param string $channelId
     * @return false|string
     * @throws \Exception
     */
    public function getChannelMessageDecrypted( Message $message , string $channelId ){
        $messageText = Crypt::binaryStringToString( Crypt::hexToBytes( $message->getText() ) );
        $messageIv = Crypt::binaryStringToString( Crypt::hexToBytes( $message->getIv() ) );
        $decrypted = Crypt::decodeWithPassphrase( $messageText , $this->getConfig()->getCipherAlgo() , $this->channelKeys[ $channelId ] , $this->getConfig()->getAesPadding() , $messageIv );
        return Crypt::unPadString( $decrypted );
    }


}