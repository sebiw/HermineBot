<?php

namespace App\Core;

/**
 *
 */
class RestClient {

    const METHOD_POST = 'POST';
    const METHOD_PUT = 'PUT';
    const METHOD_GET = 'GET';

    const RESPONSE_FORMAT_JSON = 'json';
    const RESPONSE_FORMAT_PLAIN = 'plain';

    protected $defaultResponseFormat = self::RESPONSE_FORMAT_PLAIN;

    /**
     *
     * @throws \Exception
     */
    public function __construct( $defaultResponseFormat = self::RESPONSE_FORMAT_PLAIN ){
        if( !in_array( $defaultResponseFormat , self::getValidFormats() ) ){
            throw new \Exception("Default response format not valid!");
        }
        $this->defaultResponseFormat = $defaultResponseFormat;
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
     * @return bool|string|array
     * @throws \Exception
     */
    public function post( $url , $data = null , ?string $responseFormat = null ){
        $result = $this->doRequest( self::METHOD_POST , $url , $data );

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
     * @return false|resource
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
     * @return bool|string
     * @throws \Exception
     */
    protected function doRequest( $method , $url , $data = null ){

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

        curl_setopt( $session , CURLOPT_URL, $url);
        curl_setopt( $session , CURLOPT_RETURNTRANSFER, 1);

        $result = curl_exec( $session );
        $this->closeCURLSession( $session );
        return $result;
    }

}