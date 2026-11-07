<?php

namespace App\Core;

use Psr\Log\LoggerInterface;

/**
 *
 */
class RestClient {

    const METHOD_POST = 'POST';
    const METHOD_GET = 'GET';

    const RESPONSE_FORMAT_JSON = 'json';
    const RESPONSE_FORMAT_PLAIN = 'plain';

    // Ohne Timeouts kann ein hängender Dienst den Daemon über den nächsten Cron-Lauf hinaus blockieren
    const CONNECT_TIMEOUT_SECONDS = 10;
    const TIMEOUT_SECONDS = 30;

    protected string $defaultResponseFormat = self::RESPONSE_FORMAT_PLAIN;

    protected ?LoggerInterface $logger = null;

    /**
     * @param bool $failOnError true = Verbindungsfehler und HTTP-Status außerhalb 2xx werfen eine Exception (externe Quellen für Platzhalter).
     *                          Hermine selbst meldet Fehler im Inhalt, dort bleibt es aus.
     * @throws \Exception
     */
    public function __construct( string $defaultResponseFormat = self::RESPONSE_FORMAT_PLAIN , ?LoggerInterface $logger = null , private readonly bool $failOnError = false ){
        if( !in_array( $defaultResponseFormat , self::getValidFormats() ) ){
            throw new \Exception("Default response format not valid!");
        }
        $this->defaultResponseFormat = $defaultResponseFormat;
        $this->logger = $logger;
    }

    /**
     * @return LoggerInterface|null
     */
    protected function getLogger() : ?LoggerInterface {
        return $this->logger;
    }

    /**
     * @return string[]
     */
    public static function getValidFormats(){
        return [ self::RESPONSE_FORMAT_JSON , self::RESPONSE_FORMAT_PLAIN ];
    }

    /**
     * @return string[]
     */
    public static function getValidMethods(){
        return [ self::METHOD_POST , self::METHOD_GET ];
    }

    /**
     * @param string $url
     * @param array<string, mixed>|string|null $data
     * @param string|null $responseFormat
     * @param array<string, string> $additionalHeader
     * @return mixed JSON: dekodierte Antwort, sonst Text (false bei Verbindungsfehler)
     * @throws \Exception
     */
    public function post( string $url , $data = null , ?string $responseFormat = null , array $additionalHeader = [] ){
        $result = $this->doRequest( self::METHOD_POST , $url , $data , $additionalHeader );

        if( $responseFormat === null ){
            $responseFormat = $this->defaultResponseFormat;
        }

        switch ( $responseFormat ){
            case self::RESPONSE_FORMAT_JSON :
                return $result === false ? null : json_decode( $result , true );
        }
        return $result;
    }

    /**
     * @param string $url
     * @param array<string, mixed>|null $data als Query
     * @param string|null $responseFormat
     * @param array<string, string> $additionalHeader
     * @return mixed
     * @throws \Exception
     */
    public function get( string $url , ?array $data = null , ?string $responseFormat = null , array $additionalHeader = [] ){
        $result = $this->doRequest( self::METHOD_GET , $url , $data , $additionalHeader );

        if( $responseFormat === null ){
            $responseFormat = $this->defaultResponseFormat;
        }

        switch ( $responseFormat ){
            case self::RESPONSE_FORMAT_JSON :
                return $result === false ? null : json_decode( $result , true );
        }
        return $result;
    }

    /**
     * @return \CurlHandle
     * @throws \Exception
     */
    protected function initCURLSession(): \CurlHandle {
        if( ( $curlInstance = curl_init() ) === false ){
            throw new \Exception('Could not init curl');
        }
        curl_setopt( $curlInstance , CURLOPT_CONNECTTIMEOUT , self::CONNECT_TIMEOUT_SECONDS );
        curl_setopt( $curlInstance , CURLOPT_TIMEOUT , self::TIMEOUT_SECONDS );
        return $curlInstance;
    }


    /**
     * @param string $method
     * @param string $url
     * @param array<string, mixed>|string|null $data
     * @param array<string, string> $additionalHeader
     * @return string|false Antwort, false bei Verbindungsfehler (nur ohne failOnError)
     * @throws \Exception
     */
    protected function doRequest( string $method , string $url , $data = null , array $additionalHeader = [] ): string|false {
        if( $url === '' ){
            // wie ein Verbindungsfehler: nur mit failOnError eine Exception
            $this->getLogger()?->error( sprintf( '%s without URL' , $method ) );
            if( $this->failOnError ){
                throw new \RuntimeException( sprintf( '%s without URL' , $method ) );
            }
            return false;
        }

        if( !in_array( $method , self::getValidMethods() ) ){
            throw new \Exception('Method not valid!');
        }

        $session = $this->initCURLSession();
        switch ($method)
        {
            case self::METHOD_POST:
                curl_setopt( $session, CURLOPT_POST, true);
                if ($data !== null){
                    curl_setopt( $session, CURLOPT_POSTFIELDS, $data);
                }

                break;
            case self::METHOD_GET:
            default:
                if ($data !== null ){
                    $url = sprintf("%s?%s", $url, is_array( $data ) ? http_build_query( $data ) : $data );
                }
        }

        if( !empty( $additionalHeader ) ){
            $headers = [];
            foreach( $additionalHeader AS $headerName => $headerData ){
                $headers[] = sprintf('%s: %s' , $headerName , $headerData );
            }
            curl_setopt( $session, CURLOPT_HTTPHEADER, $headers);
        }

        curl_setopt( $session , CURLOPT_URL, $url);
        curl_setopt( $session , CURLOPT_RETURNTRANSFER, true);

        // mit CURLOPT_RETURNTRANSFER liefert curl_exec() den Text oder false
        $result = curl_exec( $session );
        $result = is_string( $result ) ? $result : false;
        $httpStatus = curl_getinfo( $session , CURLINFO_HTTP_CODE );
        $curlError = curl_error( $session );
        // Die Verbindung schließt PHP selbst (curl_close() ist seit 8.5 abgekündigt)
        unset( $session );

        if( $result === false ){
            // z. B. Timeout oder Verbindungsfehler
            $this->getLogger()?->error( sprintf('%s %s failed' , $method , $url ) , [ 'error' => $curlError ] );
        }

        // Nur Adresse und Status: Anfragen und Antworten enthalten Schlüssel und Nachrichten
        $this->getLogger()?->debug( sprintf('%s %s' , $method , $url ) , [ 'status' => $httpStatus ] );

        if( $this->failOnError ){
            if( $result === false ){
                throw new \RuntimeException( sprintf( '%s %s failed: %s' , $method , self::withoutQuery( $url ) , $curlError ) );
            }
            if( $httpStatus < 200 || $httpStatus >= 300 ){
                throw new \RuntimeException( sprintf( '%s %s answered with HTTP %d' , $method , self::withoutQuery( $url ) , $httpStatus ) );
            }
        }

        return $result;
    }

    /**
     * Adresse für Fehlermeldungen ohne Query (kann Schlüssel enthalten)
     */
    private static function withoutQuery( string $url ): string
    {
        return explode( '?' , $url , 2 )[0];
    }
}
