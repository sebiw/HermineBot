<?php

namespace App\Tests\Core;

use App\Core\UserManager;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

/**
 * Benutzerverwaltung: Prüfungen ohne Datenbank
 */
class UserManagerTest extends TestCase
{
    public function testGeneratedKeysAreLongAndUrlSafe(): void
    {
        $key = UserManager::generateKey();
        $this->assertMatchesRegularExpression( '/^[A-Za-z0-9_-]{43}$/' , $key );
        $this->assertNotSame( $key , UserManager::generateKey() );
    }

    public function testActivityIsStoredAtMostOncePerMinute(): void
    {
        $user = new User();
        $now = new \DateTimeImmutable( '2026-10-26 22:00:00' );
        $this->assertTrue( $user->isSeenDue( $now ) );

        ( new \ReflectionProperty( User::class , 'lastSeenAt' ) )->setValue( $user , $now );
        $this->assertFalse( $user->isSeenDue( $now->modify( '+59 seconds' ) ) );
        $this->assertTrue( $user->isSeenDue( $now->modify( '+60 seconds' ) ) );
    }

    public function testKindAndRoles(): void
    {
        $this->assertSame( [ 'ROLE_ADMIN' ] , UserManager::rolesOf( UserManager::KIND_ADMIN ) );
        $this->assertSame( [ 'ROLE_API' ] , UserManager::rolesOf( UserManager::KIND_API ) );
        $this->assertSame( UserManager::KIND_API , UserManager::kindOf( ( new User() )->setRoles( [ 'ROLE_API' ] ) ) );
        $this->assertSame( UserManager::KIND_ADMIN , UserManager::kindOf( ( new User() )->setRoles( [ 'ROLE_ADMIN' ] ) ) );

        $this->expectException( \InvalidArgumentException::class );
        UserManager::checkKind( 'root' );
    }

    public function testPasswordLength(): void
    {
        $this->assertSame( 'lang genug!' , UserManager::checkPassword( 'lang genug!' ) );

        $this->expectException( \InvalidArgumentException::class );
        UserManager::checkPassword( 'kurz' );
    }
}
