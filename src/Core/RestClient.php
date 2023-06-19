<?php

namespace App\Core;

use Psr\Log\LoggerInterface;

/**
 *
 */
class RestClient {

    const METHOD_POST = 'POST';
    const METHOD_PUT = 'PUT';
    const METHOD_GET = 'GET';

    const RESPONSE_FORMAT_JSON = 'json';
    const RESPONSE_FORMAT_PLAIN = 'plain';

    protected string $defaultResponseFormat = self::RESPONSE_FORMAT_PLAIN;

    protected ?LoggerInterface $logger = null;

    /**
     *
     * @throws \Exception
     */
    public function __construct( $defaultResponseFormat = self::RESPONSE_FORMAT_PLAIN , ?LoggerInterface $logger = null ){
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
        return [ self::METHOD_POST , self::METHOD_GET , self::METHOD_PUT ];
    }

    /**
     * @param $url
     * @param null $data
     * @param string|null $responseFormat
     * @param array $additionalHeader
     * @return bool|string|array
     * @throws \Exception
     */
    public function post( $url , $data = null , ?string $responseFormat = null , array $additionalHeader = [] ){
        $result = $this->doRequest( self::METHOD_POST , $url , $data , $additionalHeader );

        if( $responseFormat === null ){
            $responseFormat = $this->defaultResponseFormat;
        }

        switch ( $responseFormat ){
            case self::RESPONSE_FORMAT_JSON :
                return json_decode( $result , true );
        }
        return $result;
    }

    /**
     * @param $url
     * @param $data
     * @param string|null $responseFormat
     * @param array $additionalHeader
     * @return bool|mixed|string
     * @throws \Exception
     */
    public function get( $url , $data = null , ?string $responseFormat = null , array $additionalHeader = [] ){
        $result = $this->doRequest( self::METHOD_GET , $url , $data , $additionalHeader );

        if( $responseFormat === null ){
            $responseFormat = $this->defaultResponseFormat;
        }

        switch ( $responseFormat ){
            case self::RESPONSE_FORMAT_JSON :
                return json_decode( $result , true );
        }
        return $result;
    }

    /**
     * @return false|\CurlHandle
     * @throws \Exception
     */
    protected function initCURLSession(){
        if( ( $curlInstance = curl_init() ) === false ){
            throw new \Exception('Could not init curl');
        }
        return $curlInstance;
    }

    /**
     * @param $session
     */
    protected function closeCURLSession( $session ){
        curl_close( $session );
    }

    /**
     * @param $method
     * @param $url
     * @param null $data
     * @param array $additionalHeader
     * @return bool|string
     * @throws \Exception
     */
    protected function doRequest( $method , $url , $data = null , array $additionalHeader = [] ){

        if( !in_array( $method , self::getValidMethods() ) ){
            throw new \Exception('Method not valid!');
        }

        $session = $this->initCURLSession();
        switch ($method)
        {
            case self::METHOD_POST:
                curl_setopt( $session, CURLOPT_POST, 1);
                if ($data !== null){
                    curl_setopt( $session, CURLOPT_POSTFIELDS, $data);
                }

                break;
            case self::METHOD_PUT:
                curl_setopt( $session , CURLOPT_PUT, 1);
                break;

            case self::METHOD_GET:
            default:
                if ($data !== null ){
                    $url = sprintf("%s?%s", $url, http_build_query($data));
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
        curl_setopt( $session , CURLOPT_RETURNTRANSFER, 1);

        $result = curl_exec( $session );
        $this->closeCURLSession( $session );

        $this->getLogger()?->info( sprintf('%s %s' , $method , $url ) , [ 'status' => curl_getinfo( $session , CURLINFO_HTTP_CODE ) ] );

        $this->getLogger()?->debug( sprintf('%s %s' , $method , $url ) , [ 'additionalHeader' => $additionalHeader , 'data' => $data , 'result' => $result ] );

        return $result;
    }

}