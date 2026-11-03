<?php

namespace App\Tests\Core;

use App\Core\MessageDecorator;
use App\Stashcat\Entities\Group;
use PHPUnit\Framework\TestCase;

/**
 * {{CompanyGroupsStats}}: Gruppen mit gleicher Nutzerzahl dürfen sich nicht verdrängen
 */
class GroupStatsTest extends TestCase
{
    public function testGleicheZahlBleibtErhaltenUndSortiert(): void
    {
        $groups = [
            new Group( [ 'name' => 'Elektro' , 'count' => 12 ] ),
            new Group( [ 'name' => 'Bergung' , 'count' => 12 ] ),
            new Group( [ 'name' => 'Stab' , 'count' => 5 ] ),
        ];
        self::assertSame(
            "Stab: 5 mögliche Nutzer\r\nBergung: 12 mögliche Nutzer\r\nElektro: 12 mögliche Nutzer",
            MessageDecorator::groupStatsText( $groups )
        );
    }
}
