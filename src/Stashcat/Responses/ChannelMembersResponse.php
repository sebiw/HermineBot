<?php

namespace App\Stashcat\Responses;

use App\Stashcat\Entities\User;

class ChannelMembersResponse extends DefaultResponse {

    /**
     * @return array|User[]
     * @throws \Exception
     */
    public function getMembers(): array
    {
        $members = [];
        foreach( $this->getResults('payload' , 'members' ) AS $member ){
            $members[ $member['id'] ] = new User( $member );
        }
        return $members;
    }

}
