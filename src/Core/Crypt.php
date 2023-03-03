<?php

namespace App\Core;

/**
 *
 */
class Crypt {

    /**
     * @param string $decodedConversationKey
     * @return string
     */
    public static function binaryStringToString( string $decodedConversationKey ){
        $data = array_map('mb_ord' , mb_str_split( $decodedConversationKey ) );
        return implode("" , array_map( 'chr' , $data ) );
    }

    /**
     * @param string $u
     * @return false|string
     */
    public static function hexToBytes( string  $u ){
        $k = "";
        $U = 0;
        for ( (!0 & strlen( $u ) && (($U = 1) && ($k .= mb_chr(hexdec($u[0]))))); $U < strlen($u); $U += 2 ){
            $k .= mb_chr(hexdec( substr($u, $U , 2)));
        }
        return $k;
    }

    /**
     * @param string $decodedConversationKey
     * @return string
     */
    public static function textEncoder( string $decodedConversationKey){
        $f = array_map('mb_ord' , mb_str_split( $decodedConversationKey ) );
        return implode( "" , array_map("mb_chr" , $f ) );
    }

    /**
     * @param array $bytes
     * @return string
     */
    public static function bytesToHex( string $bytes ){
        $r = "";
        foreach( array_map('ord' , str_split( $bytes ) ) AS $f ){
            $r .= sprintf('%02x', $f );
        }
        return $r;
    }


    /**
     * @param $private_key
     * @param $passphrase
     * @return false|resource
     * @throws \Exception
     */
    public static function decodePrivateKey($private_key , $passphrase){
        $result = openssl_pkey_get_private( $private_key , $passphrase );
        self::throwOnError();
        return $result;
    }

    /**
     * @param $data
     * @param $private_key
     * @param $padding
     * @return mixed
     * @throws \Exception
     */
    public static function decodeWithPrivateKey( $data , $private_key , $padding ){
        openssl_private_decrypt( $data , $result  , $private_key , $padding );
        self::throwOnError();
        return $result;
    }

    /**
     * @param string $messageText
     * @param string $algo
     * @param string $passphrase
     * @param $padding
     * @param $messageIv
     * @return false|string
     * @throws \Exception
     */
    public static function decodeWithPassphrase( string $messageText , string $algo , string $passphrase , $padding , $messageIv ){
        $result = openssl_decrypt( $messageText, $algo, $passphrase, $padding, $messageIv );
        self::throwOnError();
        return $result;
    }

    /**
     * @param string $messageText
     * @param string $algo
     * @param string $passphrase
     * @param $padding
     * @param $messageIv
     * @return false|string
     * @throws \Exception
     */
    public static function encodeWithChannelKey( string $messageText , string $algo , string $passphrase , $padding , $messageIv ){
        $result = openssl_encrypt( $messageText , $algo , $passphrase ,  $padding , $messageIv );
        self::throwOnError();
        return $result;
    }

    /**
     * @param string $messageText
     * @param string $algo
     * @param string $passphrase
     * @param $padding
     * @param $messageIv
     * @return false|string
     * @throws \Exception
     */
    public static function encodeWithConversationKey( string $messageText , string $algo , string $passphrase , $padding , $messageIv ){
        // Alias-Function maybe required in the future
        return self::encodeWithChannelKey( $messageText , $algo , $passphrase , $padding , $messageIv );
    }

    /**
     * @param string $message
     * @param int $length
     * @return string
     */
    public static function padString( string $message , int $length ): string
    {
        $pad = $length - (strlen( $message ) % $length);
        return $message . str_repeat(mb_chr($pad), $pad);
    }

    /**
     * @param string $message
     * @return string
     */
    public static function unPadString( string $message ): string
    {
        $len = strlen( $message );
        $pad = ord( $message[$len-1]); // Last char = Pad Char & Pad Length
        return substr( $message, 0, strlen( $message ) - $pad );
    }

    /**
     * @return array
     */
    public static function getErrors(): array
    {
        $errors = [];
        while ($msg = openssl_error_string()){
            $errors[] = $msg;
        }
        return $errors;
    }

    /**
     * @throws \Exception
     */
    protected static function throwOnError(){
        $errors = self::getErrors();
        if( !empty( $errors ) ){
            throw new \Exception( implode(", " , $errors ) );
        }
    }

}