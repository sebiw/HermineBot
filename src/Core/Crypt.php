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
     * @param string $data
     * @return string
     */
    public static function base64UrlEncode( string $data ): string
    {
        return rtrim( strtr( base64_encode( $data ) , '+/' , '-_' ) , '=' );
    }

    /**
     * @param string $data
     * @return string
     * @throws \Exception
     */
    public static function base64UrlDecode( string $data ): string
    {
        $result = base64_decode( strtr( $data , '-_' , '+/' ) . str_repeat( '=' , ( 4 - strlen( $data ) % 4 ) % 4 ) , true );
        if( $result === false ){
            throw new \Exception('Invalid base64url data!');
        }
        return $result;
    }

    /**
     * Zufallstext wie im Web-Client (z. B. unique_identifier einer Unterhaltung)
     * @param int $length
     * @return string
     * @throws \Exception
     */
    public static function randomString( int $length ): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $result = '';
        for( $i = 0 ; $i < $length ; $i++ ){
            $result .= $chars[ random_int( 0 , strlen( $chars ) - 1 ) ];
        }
        return $result;
    }

    /**
     * ASN.1-DER: Länge
     * @param int $length
     * @return string
     */
    protected static function derLength( int $length ): string
    {
        if( $length < 0x80 ){
            return chr( $length );
        }
        $bytes = ltrim( pack( 'N' , $length ) , "\x00" );
        return chr( 0x80 | strlen( $bytes ) ) . $bytes;
    }

    /**
     * ASN.1-DER: Element mit Tag
     * @param int $tag
     * @param string $content
     * @return string
     */
    protected static function derElement( int $tag , string $content ): string
    {
        return chr( $tag ) . self::derLength( strlen( $content ) ) . $content;
    }

    /**
     * ASN.1-DER: vorzeichenlose Ganzzahl (Big Endian)
     * @param string $bytes
     * @return string
     */
    protected static function derUnsignedInteger( string $bytes ): string
    {
        $bytes = ltrim( $bytes , "\x00" );
        if( $bytes === '' || ord( $bytes[0] ) > 0x7f ){
            $bytes = "\x00" . $bytes;
        }
        return self::derElement( 0x02 , $bytes );
    }

    /**
     * @param string $der
     * @param string $label
     * @return string
     */
    protected static function derToPem( string $der , string $label ): string
    {
        return "-----BEGIN " . $label . "-----\n" . chunk_split( base64_encode( $der ) , 64 , "\n" ) . "-----END " . $label . "-----\n";
    }

    /**
     * Privaten RSA-Schlüssel aus einem JWK (WebCrypto) laden
     * @param array $jwk
     * @return \OpenSSLAsymmetricKey
     * @throws \Exception
     */
    public static function rsaPrivateKeyFromJwk( array $jwk ): \OpenSSLAsymmetricKey
    {
        if( ( $jwk['kty'] ?? null ) !== 'RSA' ){
            throw new \Exception('JWK is not a RSA key!');
        }
        // PKCS#1 RSAPrivateKey
        $der = self::derUnsignedInteger( "\x00" );
        foreach( [ 'n' , 'e' , 'd' , 'p' , 'q' , 'dp' , 'dq' , 'qi' ] AS $key ){
            if( !isset( $jwk[ $key ] ) ){
                throw new \Exception( sprintf( 'JWK incomplete, %s missing!' , $key ) );
            }
            $der .= self::derUnsignedInteger( self::base64UrlDecode( $jwk[ $key ] ) );
        }
        self::getErrors(); // alte Meldungen verwerfen
        $result = openssl_pkey_get_private( self::derToPem( self::derElement( 0x30 , $der ) , 'RSA PRIVATE KEY' ) );
        // OpenSSL 3 hinterlässt auch bei Erfolg Meldungen (z. B. „no start line“), daher nur das Ergebnis prüfen
        $errors = self::getErrors();
        if( $result === false ){
            throw new \Exception( sprintf( 'Private key from JWK invalid: %s' , implode( ', ' , $errors ) ) );
        }
        return $result;
    }

    /**
     * Öffentlichen RSA-Schlüssel aus einem JWK (WebCrypto) laden
     * @param array $jwk
     * @return \OpenSSLAsymmetricKey
     * @throws \Exception
     */
    public static function rsaPublicKeyFromJwk( array $jwk ): \OpenSSLAsymmetricKey
    {
        if( ( $jwk['kty'] ?? null ) !== 'RSA' || !isset( $jwk['n'] , $jwk['e'] ) ){
            throw new \Exception('JWK is not a RSA public key!');
        }
        // SubjectPublicKeyInfo mit rsaEncryption (1.2.840.113549.1.1.1)
        $rsaPublicKey = self::derElement( 0x30 , self::derUnsignedInteger( self::base64UrlDecode( $jwk['n'] ) ) . self::derUnsignedInteger( self::base64UrlDecode( $jwk['e'] ) ) );
        $algorithm = self::derElement( 0x30 , self::derElement( 0x06 , "\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01" ) . "\x05\x00" );
        $der = self::derElement( 0x30 , $algorithm . self::derElement( 0x03 , "\x00" . $rsaPublicKey ) );
        self::getErrors(); // alte Meldungen verwerfen
        $result = openssl_pkey_get_public( self::derToPem( $der , 'PUBLIC KEY' ) );
        // OpenSSL 3 hinterlässt auch bei Erfolg Meldungen (z. B. „no start line“), daher nur das Ergebnis prüfen
        $errors = self::getErrors();
        if( $result === false ){
            throw new \Exception( sprintf( 'Public key from JWK invalid: %s' , implode( ', ' , $errors ) ) );
        }
        return $result;
    }

    /**
     * Fingerabdruck eines RSA-Schlüssels wie im Web-Client: base64( SHA-256( kty + e + n ) ), e/n als base64url
     * @param \OpenSSLAsymmetricKey $key
     * @return string
     * @throws \Exception
     */
    public static function rsaKeyFingerprint( \OpenSSLAsymmetricKey $key ): string
    {
        $details = openssl_pkey_get_details( $key );
        if( !isset( $details['rsa']['n'] , $details['rsa']['e'] ) ){
            throw new \Exception('Key is not a RSA key!');
        }
        $jwkString = 'RSA' . self::base64UrlEncode( ltrim( $details['rsa']['e'] , "\x00" ) ) . self::base64UrlEncode( ltrim( $details['rsa']['n'] , "\x00" ) );
        return base64_encode( hash( 'sha256' , $jwkString , true ) );
    }

    /**
     * RSASSA-PKCS1-v1_5 mit SHA-256, Ergebnis hex
     * @param string $data
     * @param \OpenSSLAsymmetricKey $privateKey
     * @return string
     * @throws \Exception
     */
    public static function signRsaSha256( string $data , \OpenSSLAsymmetricKey $privateKey ): string
    {
        self::getErrors(); // alte Meldungen verwerfen
        if( !openssl_sign( $data , $signature , $privateKey , OPENSSL_ALGO_SHA256 ) ){
            self::throwOnError();
            throw new \Exception('Signing failed!');
        }
        self::getErrors(); // Meldungen trotz Erfolg nicht an spätere Aufrufe weitergeben
        return bin2hex( $signature );
    }

    /**
     * @param string $data
     * @param string $signatureHex
     * @param \OpenSSLAsymmetricKey $publicKey
     * @return bool
     */
    public static function verifyRsaSha256( string $data , string $signatureHex , \OpenSSLAsymmetricKey $publicKey ): bool
    {
        $signature = ctype_xdigit( $signatureHex ) && strlen( $signatureHex ) % 2 === 0 ? hex2bin( $signatureHex ) : false;
        $result = $signature !== false && openssl_verify( $data , $signature , $publicKey , OPENSSL_ALGO_SHA256 ) === 1;
        self::getErrors(); // Fehlerpuffer leeren
        return $result;
    }

    /**
     * @param string $data
     * @param string $publicKeyPem
     * @param int $padding
     * @return string
     * @throws \Exception
     */
    public static function encodeWithPublicKey( string $data , string $publicKeyPem , int $padding ): string
    {
        self::getErrors(); // alte Meldungen verwerfen
        if( !openssl_public_encrypt( $data , $result , $publicKeyPem , $padding ) ){
            self::throwOnError();
            throw new \Exception('Public key encryption failed!');
        }
        self::getErrors(); // Meldungen trotz Erfolg (OpenSSL 3, PEM-Text) nicht an spätere Aufrufe weitergeben
        return $result;
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