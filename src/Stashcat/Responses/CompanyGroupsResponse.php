<?php

namespace App\Stashcat\Responses;

use App\Stashcat\Entities\Company;
use App\Stashcat\Entities\Group;

class CompanyGroupsResponse extends DefaultResponse {

    /**
     * @return array
     * @throws \Exception
     */
    public function getGroupIds(): array
    {
        $ids = [];
        foreach( $this->getResults("payload" , "groups") AS $key => $group ){
            $ids[] = $group["id"];
        }
        return $ids;
    }

    /**
     * @return array|Group[]
     * @throws \Exception
     */
    public function getGroups(): array
    {
        $groups = [];
        foreach( $this->getResults('payload' , 'groups' ) AS $group ){
            $groups[ $group['id'] ] = new Group( $group );
        }
        return $groups;
    }

    /**
     * @param $name
     * @return Group|null
     * @throws \Exception
     */
    public function getGroupByName( $name ) : ?Group {
        foreach( $this->getGroups() AS $group ){
            if( strtoupper( $group->getName() ) == strtoupper( $name ) ){
                return $group;
            }
        }
        return null;
    }

}