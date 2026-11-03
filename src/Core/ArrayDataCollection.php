<?php

namespace App\Core;

class ArrayDataCollection {

    /** @var array<mixed> Antwort bzw. Konfiguration als verschachteltes Array */
    protected array $results;

    /**
     * @param array<mixed> $results
     */
    public function __construct( array $results )
    {
        $this->results = $results;
    }

    /**
     * @param string|int ...$keys Pfad im Array
     * @return mixed
     * @throws \Exception
     */
    protected function getResults( string|int ...$keys ): mixed
    {
        $current = &$this->results;
        foreach( $keys AS $key ){
            if( isset( $current[ $key ] ) ){
                $current = &$current[ $key ];
            } else {
                // Nur die vorhandenen Schlüssel nennen, nicht die Werte (können Client-Key, Passwörter o. Ä. enthalten)
                throw new \Exception(sprintf('Key %s not found, available keys: %s' , $key , is_array( $current ) ? implode(', ' , array_keys( $current ) ) : gettype( $current ) ));
            }
        }
        return $current;
    }

    /**
     * @param mixed $data
     * @param string|int ...$keys
     * @return void
     * @throws \Exception
     */
    protected function setResult( mixed $data , string|int ...$keys ): void {
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
     * @param string|int ...$keys
     * @return bool
     */
    protected function resultKeyExist( string|int ...$keys ): bool
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

}