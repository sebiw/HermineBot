<?php

namespace App\Stashcat\Responses;

use App\Stashcat\Entities\User;

class UserInfoResponse extends DefaultResponse {

    /**
     * @return User
     * @throws \Exception
     */
    public function getUser(): User
    {
        return new User( $this->getResults('payload' , 'user' ) );
    }

}
