<?php

namespace App\Core;

class THWStashcatConventions {

    /**
     * Extracts "OrgaUnit" and "LastName" from the original stashcat "LastName"
     * @param string $lastName
     * @return array|null
     */
    public static function extractNameParts( string $lastName ): ?array
    {
        preg_match("#(?P<last_name>.*) \((?P<department>.*)\)#" , $lastName , $matches );
        if( isset( $matches['last_name'] , $matches['department'] ) ){
            return [ 'last_name' => $matches['last_name'] , 'department' => $matches['department'] ];
        }
        return null;
    }

}