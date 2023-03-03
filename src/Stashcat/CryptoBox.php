<?php

namespace App\Stashcat;

use App\Core\Crypt;
use App\Stashcat\Entities\Message;
use App\Stashcat\Responses\PrivateKeyResponse;
use PHPUnit\Util\Exception;

class CryptoBox {

    private $privateKey = null;

    private $channelKeys = [];

    private $conversationKeys = [];

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
        $this->channelKeys[ $channelId ] = Crypt::decodeWithPrivateKey( $channelKeyBase64Decoded , $this->privateKey ,  $this->getConfig()->getRsaOaepPadding() );
    }


    /**
     * @param string $encodedConversationKey
     * @param string $conversationId
     * @throws \Exception
     */
    public function setConversationEncryptionConversation( string $encodedConversationKey , string $conversationId ){
        $conversationKeyBase64Decoded = base64_decode( $encodedConversationKey, true );
        $this->conversationKeys[ $conversationId ] = Crypt::decodeWithPrivateKey( $conversationKeyBase64Decoded , $this->privateKey ,  $this->getConfig()->getRsaOaepPadding() );
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

        if( !isset( $this->channelKeys[ $channelId ] ) ){
            throw new Exception(sprintf('Channel-Key %s not available!' , $channelId ));
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
     * @return string|null
     * @throws \Exception
     */
    public function getConversationMessageEncrypted( string $messageToSend , string $conversationId , string &$iv ) : ?string {
        $iv = $this->getRandomIv();
        $result = Crypt::textEncoder( $messageToSend );
        // Pad own content...
        $result = Crypt::padString( $result , $this->getConfig()->getCipherAlgoPaddingBlockSize() );
        // Encrypt

        if( !isset( $this->conversationKeys[ $conversationId ] ) ){
            throw new Exception(sprintf('Conversation-Key %s not available!' , $conversationId ));
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
     * @return false|string
     * @throws \Exception
     */
    public function getChannelMessageDecrypted( Message $message , string $channelId ){
        $messageText = Crypt::binaryStringToString( Crypt::hexToBytes( $message->getText() ) );
        $messageIv = Crypt::binaryStringToString( Crypt::hexToBytes( $message->getIv() ) );
        $decrypted = Crypt::decodeWithPassphrase( $messageText , $this->getConfig()->getCipherAlgo() , $this->channelKeys[ $channelId ] , $this->getConfig()->getAesPadding() , $messageIv );
        return Crypt::unPadString( $decrypted );
    }

    /**
     * @param Message $message
     * @param string $conversationId
     * @return false|string
     * @throws \Exception
     */
    public function getConversationMessageDecrypted( Message $message , string $conversationId ){
        $messageText = Crypt::binaryStringToString( Crypt::hexToBytes( $message->getText() ) );
        $messageIv = Crypt::binaryStringToString( Crypt::hexToBytes( $message->getIv() ) );
        $decrypted = Crypt::decodeWithPassphrase( $messageText , $this->getConfig()->getCipherAlgo() , $this->conversationKeys[ $conversationId ] , $this->getConfig()->getAesPadding() , $messageIv );
        return Crypt::unPadString( $decrypted );
    }


}