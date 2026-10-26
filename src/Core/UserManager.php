<?php

namespace App\Core;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Benutzer des Bots: Administratoren (Oberfläche) und API-Benutzer (REST, z. B. für den StAN-Manager). Bei API-Benutzern
 * ist der „Key“ das Passwort; er wird erzeugt und nur einmal angezeigt. Gespeichert wird immer nur der Hash.
 * Schutz: niemand löscht sich selbst oder nimmt sich die Administration, mindestens ein Administrator bleibt.
 */
class UserManager
{
    public const KIND_ADMIN = 'admin';
    public const KIND_API = 'api';

    public const KINDS = [
        self::KIND_ADMIN => 'Administrator (Oberfläche)',
        self::KIND_API => 'API-Benutzer (REST)',
    ];

    public const PASSWORD_MIN_LENGTH = 10;

    public function __construct(
        private readonly EntityManagerInterface $entityManager ,
        private readonly UserRepository $userRepository ,
        private readonly UserPasswordHasherInterface $passwordHasher ,
        #[Autowire(service: 'logger.app')] private readonly DatabaseLogger $logger
    )
    {
    }

    public static function kindOf( User $user ): string
    {
        return in_array( 'ROLE_ADMIN' , $user->getRoles() , true ) ? self::KIND_ADMIN : self::KIND_API;
    }

    /**
     * @return User[] by name
     */
    public function all(): array
    {
        return $this->userRepository->findBy( [] , [ 'username' => 'ASC' ] );
    }

    /**
     * New user. Administrators get the given password, API users a generated key (returned once).
     * @return array{0: User, 1: string|null} user, generated key (API users)
     * @throws \InvalidArgumentException with a message for the user
     */
    public function create( string $username , string $kind , ?string $password , string $by ): array
    {
        $username = $this->checkUsername( $username , null );
        $kind = self::checkKind( $kind );
        $key = null;
        if( $kind === self::KIND_API ){
            $password = $key = self::generateKey();
        } else {
            $password = self::checkPassword( (string) $password );
        }
        $user = ( new User() )->setUsername( $username )->setRoles( self::rolesOf( $kind ) );
        $user->setPassword( $this->passwordHasher->hashPassword( $user , $password ) );
        $this->entityManager->persist( $user );
        $this->entityManager->flush();
        $this->logger->info( sprintf( 'Benutzer „%s“ angelegt (%s)' , $username , self::KINDS[ $kind ] ) , [ 'by' => $by ] );
        return [ $user , $key ];
    }

    /**
     * @param string|null $password new password (administrators); empty = unchanged
     * @throws \InvalidArgumentException with a message for the user
     */
    public function update( User $user , string $username , string $kind , ?string $password , User $current ): void
    {
        $username = $this->checkUsername( $username , $user );
        $kind = self::checkKind( $kind );
        if( $user === $current && $kind !== self::KIND_ADMIN ){
            throw new \InvalidArgumentException( 'Die eigene Administration lässt sich nicht entfernen.' );
        }
        if( self::kindOf( $user ) === self::KIND_ADMIN && $kind !== self::KIND_ADMIN ){
            $this->assertAnotherAdmin( $user );
        }
        $user->setUsername( $username )->setRoles( self::rolesOf( $kind ) );
        if( $kind === self::KIND_ADMIN && trim( (string) $password ) !== '' ){
            $user->setPassword( $this->passwordHasher->hashPassword( $user , self::checkPassword( (string) $password ) ) );
        }
        $this->entityManager->flush();
        $this->logger->info( sprintf( 'Benutzer „%s“ gespeichert (%s)' , $username , self::KINDS[ $kind ] ) , [ 'by' => $current->getUserIdentifier() ] );
    }

    /**
     * New key for an API user – the old one stops working at once
     */
    public function renewKey( User $user , string $by ): string
    {
        if( self::kindOf( $user ) !== self::KIND_API ){
            throw new \InvalidArgumentException( 'Nur API-Benutzer haben einen Schlüssel.' );
        }
        $key = self::generateKey();
        $user->setPassword( $this->passwordHasher->hashPassword( $user , $key ) );
        $this->entityManager->flush();
        $this->logger->info( sprintf( 'Neuer Schlüssel für API-Benutzer „%s“' , $user->getUserIdentifier() ) , [ 'by' => $by ] );
        return $key;
    }

    /**
     * @throws \InvalidArgumentException with a message for the user
     */
    public function delete( User $user , User $current ): void
    {
        if( $user === $current ){
            throw new \InvalidArgumentException( 'Das eigene Konto lässt sich nicht löschen.' );
        }
        if( self::kindOf( $user ) === self::KIND_ADMIN ){
            $this->assertAnotherAdmin( $user );
        }
        $name = $user->getUserIdentifier();
        $this->entityManager->remove( $user );
        $this->entityManager->flush();
        $this->logger->info( sprintf( 'Benutzer „%s“ gelöscht' , $name ) , [ 'by' => $current->getUserIdentifier() ] );
    }

    /**
     * 32 random bytes, URL-safe (no characters that need escaping in JSON or configuration files)
     */
    public static function generateKey(): string
    {
        return rtrim( strtr( base64_encode( random_bytes( 32 ) ) , '+/' , '-_' ) , '=' );
    }

    /**
     * @return string[]
     */
    public static function rolesOf( string $kind ): array
    {
        return $kind === self::KIND_ADMIN ? [ 'ROLE_ADMIN' ] : [ 'ROLE_API' ];
    }

    public static function checkKind( string $kind ): string
    {
        if( !isset( self::KINDS[ $kind ] ) ){
            throw new \InvalidArgumentException( 'Bitte die Art des Benutzers wählen.' );
        }
        return $kind;
    }

    public static function checkPassword( string $password ): string
    {
        if( mb_strlen( $password ) < self::PASSWORD_MIN_LENGTH ){
            throw new \InvalidArgumentException( sprintf( 'Das Passwort muss mindestens %d Zeichen lang sein.' , self::PASSWORD_MIN_LENGTH ) );
        }
        return $password;
    }

    private function checkUsername( string $username , ?User $self ): string
    {
        $username = trim( $username );
        if( preg_match( '/^[A-Za-z0-9._@-]{3,180}$/' , $username ) !== 1 ){
            throw new \InvalidArgumentException( 'Der Benutzername braucht 3 bis 180 Zeichen: Buchstaben ohne Umlaute, Ziffern, Punkt, Bindestrich, Unterstrich oder @.' );
        }
        $existing = $this->userRepository->findOneBy( [ 'username' => $username ] );
        if( $existing !== null && $existing !== $self ){
            throw new \InvalidArgumentException( sprintf( 'Den Benutzer „%s“ gibt es schon.' , $username ) );
        }
        return $username;
    }

    private function assertAnotherAdmin( User $user ): void
    {
        foreach( $this->all() AS $other ){
            if( $other !== $user && self::kindOf( $other ) === self::KIND_ADMIN ){
                return;
            }
        }
        throw new \InvalidArgumentException( 'Es muss mindestens ein Administrator bleiben.' );
    }
}
