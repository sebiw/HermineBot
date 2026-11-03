<?php

namespace App\Tests\Security;

use App\Security\ApiKeyAuthenticator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

/**
 * Anmeldung an der API: falsch geformte Angaben ergeben 401, nicht 500
 */
class ApiKeyAuthenticatorTest extends TestCase
{
    private function request( string $body ): Request
    {
        return new Request( [] , [] , [] , [] , [] , [ 'CONTENT_TYPE' => 'application/json' ] , $body );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidBodies(): iterable
    {
        yield 'Name als Liste' => [ '{"user":["x"],"key":"y"}' ];
        yield 'Schlüssel als Zahl' => [ '{"user":"x","key":5}' ];
        yield 'kein Objekt' => [ '"x"' ];
        yield 'leer' => [ '' ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidBodies')]
    public function testUngueltigeAngaben( string $body ): void
    {
        $this->expectException( CustomUserMessageAuthenticationException::class );
        ( new \ReflectionClass( ApiKeyAuthenticator::class ) )->newInstanceWithoutConstructor()->authenticate( $this->request( $body ) );
    }

    public function testGueltigeAngabenErgebenPassport(): void
    {
        $passport = ( new \ReflectionClass( ApiKeyAuthenticator::class ) )->newInstanceWithoutConstructor()->authenticate( $this->request( '{"user":"stan","key":"geheim"}' ) );
        self::assertSame( 'stan' , $passport->getBadge( \Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge::class )?->getUserIdentifier() );
    }
}
