<?php

namespace App\Tests\Stashcat;

use App\Controller\ApiController;
use App\Stashcat\Entities\User;
use PHPUnit\Framework\TestCase;

/**
 * Namensteile der Hermine-Accounts für /api/channel-members (erfundene Namen)
 */
class UserNamePartsTest extends TestCase
{
    public function testNameWithDepartment(): void
    {
        $user = new User( [ 'id' => 123 , 'first_name' => 'Erika' , 'last_name' => 'Musterfrau (OV Beispielstadt)' ] );

        $this->assertSame( [ 'id' => '123' , 'first_name' => 'Erika' , 'last_name' => 'Musterfrau' , 'department' => 'OV Beispielstadt' , 'display_name' => 'Erika Musterfrau (OV Beispielstadt)' ] , ApiController::memberData( $user ) );
    }

    public function testNameWithoutDepartmentAndDeletedAccount(): void
    {
        $this->assertSame( [ 'first_name' => 'Max' , 'last_name' => 'Mustermann' , 'department' => null ] , ( new User( [ 'id' => 1 , 'first_name' => 'Max' , 'last_name' => 'Mustermann' ] ) )->getNameParts() );
        $this->assertSame( [ 'first_name' => '' , 'last_name' => '' , 'department' => null ] , ( new User( [ 'id' => 2 , 'deleted' => true ] ) )->getNameParts() );
    }
}
