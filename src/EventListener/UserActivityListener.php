<?php

namespace App\EventListener;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Merkt sich, wann ein Benutzer zuletzt aktiv war – Administratoren in der Oberfläche und API-Benutzer bei jedem
 * REST-Aufruf (Anmeldung über den Schlüssel). Läuft nach der Firewall (Priorität 8) und schreibt höchstens einmal pro
 * Minute, und zwar gezielt nur diese Spalte (kein flush, der andere offene Änderungen mitnehmen würde).
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 0)]
final class UserActivityListener
{
    public function __construct(
        private readonly Security $security ,
        private readonly EntityManagerInterface $entityManager
    )
    {
    }

    public function __invoke( RequestEvent $event ): void
    {
        $user = $event->isMainRequest() ? $this->security->getUser() : null;
        $now = new \DateTimeImmutable();
        if( !$user instanceof User || !$user->isSeenDue( $now ) ){
            return;
        }
        try {
            $this->entityManager->createQuery( 'UPDATE ' . User::class . ' u SET u.lastSeenAt = :now WHERE u.id = :id' )
                ->setParameter( 'now' , $now , 'datetime_immutable' )
                ->setParameter( 'id' , $user->getId() )
                ->execute();
        } catch( \Throwable ){
            // Only a display – never let it break a request
        }
    }
}
