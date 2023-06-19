<?php

namespace App\Core;

class ArrayDataCollection {

    protected array $results;

    public function __construct( array $results )
    {
        $this->results = $results;
    }

    /**
     * @param ...$keys
     * @return array|mixed
     * @throws \Exception
     */
    protected function getResults( ...$keys )
    {
        $current = &$this->results;
        foreach( $keys AS $key ){
            if( isset( $current[ $key ] ) ){
                $current = &$current[ $key ];
            } else {
                throw new \Exception(sprintf('Key %s not found in: %s' , $key , json_encode( $this->results ) ));
            }
        }
        return $current;
    }

    /**
     * @param $data
     * @param ...$keys
     * @return void
     * @throws \Exception
     */
    protected function setResult( $data , ...$keys ){
        $current = &$this->results;
        foreach( $keys AS $key ){
            if( isset( $current[ $key ] ) ){
                $current = &$current[ $key ];
            } else {
                throw new \Exception('Key not found!');
            }
        }
        $current = $data;
    }

    /**
     * @param ...$keys
     * @return bool
     */
    protected function resultKeyExist( ...$keys ): bool
    {
        $current = &$this->results;
        foreach( $keys AS $key ){
            if( isset( $current[ $key ] ) ){
                $current = &$current[ $key ];
            } else {
                return false;
            }
        }
        return true;
    }

    public function dumpResults(){
        var_dump( $this->results );
    }

}