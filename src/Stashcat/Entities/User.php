<?php

namespace App\Stashcat\Entities;

use App\Core\THWStashcatConventions;

class User extends DefaultEntity {

    public function getFirstName(){
        return $this->getResults('first_name');
    }

    public function getLastName(){
        return $this->getResults('last_name');
    }

    public function getId(){
        return $this->getResults('id');
    }

    /**
     * @return array [ 'first_name' => $firstName , 'last_name' => $lastName , 'department' => $department ]
     */
    public function getParsedName(): array
    {
        $nameParts = THWStashcatConventions::extractNameParts( $this->getLastName() );
        $lastName = $nameParts['last_name'];
        $firstName = $this->getFirstName();
        $department = $nameParts['department'];
        return [ 'first_name' => $firstName , 'last_name' => $lastName , 'department' => $department ];
    }

    /**
     * @return string
     */
    public function getCompleteName(): string
    {
        return $this->getFirstName() . ' ' . $this->getLastName();
    }

}