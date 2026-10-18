<?php

namespace App\Stashcat\Responses;

use App\Stashcat\Entities\User;

class UsersListingResponse extends DefaultResponse {

    /**
     * @return array|User[]
     * @throws \Exception
     */
    public function getUsers(): array
    {
        $users = [];
        foreach( $this->getResults('payload' , 'users' ) AS $user ){
            $users[ $user['id'] ] = new User( $user );
        }
        return $users;
    }

}
