<?php

namespace App\Controller;

use App\Core\DatabaseLogger;
use App\Entity\Event;
use App\Service\AppService;
use DateInterval;
use DateTime;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LogLevel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class JobController extends AbstractController
{

    #[Route('/', name: 'handler', methods: [ 'GET' ])]
    public function main( AppService $app , ManagerRegistry $doctrine , #[Autowire(service: 'logger.events')] DatabaseLogger $logger , string $context = 'Console'): Response
    {

        $logger->log(LogLevel::INFO , sprintf('%s: Started event handler' , $context ) );

        $jsonResult = [];
        $now = new DateTime();
        $entityManager = $doctrine->getManagerForClass( Event::class );

        /* @var $events Event[] */
        $events = $entityManager->getRepository( Event::class )
            ->createQueryBuilder('entries')
            ->select('entries')
            // Entweder: das Fälligkeitsdatum liegt in der Vergangenheit und es wurde noch nicht abgearbeitet
            ->where('( entries.dueDateTime <= :now AND entries.doneDateTime IS NULL )')
            // ODER: das Fälligkeitsdatum liegt in der Vergangenheit, und das (Datum der letzten abarbeitung ist kleiner gleich des Enddatums oder das Enddatum ist NULL oder es wurde noch nie abgearbeitet)
            ->orWhere('( entries.dueDateTime <= :now AND ( entries.doneDateTime < entries.untilDateTime OR entries.untilDateTime IS NULL ) AND entries.dateInterval IS NOT NULL )')
            ->setParameter('now' , $now  ) // Und das Fälligkeitsdatum liegt in der vergangenheit
            ->getQuery()->getResult();

        $eventsProcessed = 0;
        if( count( $events ) > 0 ){
            // Mediator -> ease of access
            $stashcatMediator = $app->getMediator();
            $stashcatMediator->login();
            $stashcatMediator->decryptPrivateKey();
            $stashcatMediator->loadCompanies();

            $THWCompany = $stashcatMediator->getCompanyMembers()->getCompanyByName('THW');
            $stashcatMediator->loadChannelSubscripted( $THWCompany );

            foreach( $events AS $event ){

                $THWChannel = $stashcatMediator->getChannelOfCompany( $event->getChannelTarget() , $THWCompany );
                $stashcatMediator->decryptChannelPrivateKey( $THWChannel );
                $result = $stashcatMediator->sendMessageToChannel( $event->getText() . $app->getAppConfig()->getAutoAppendToMessages() , $THWChannel );

                // Update next due date time...
                if( !empty( $event->getDateInterval() ) ){

                    $interval = new DateInterval( $event->getDateInterval() );
                    // If the process wasn't able to work on multiple intervals in the past - try to catch up.
                    $dueDate = clone $event->getDueDateTime();
                    while( $dueDate <= $now ){
                        $dueDate->add( $interval );
                    }
                    $event->setDueDateTime( $dueDate );
                }

                $event->increaseTransmissionsCount();

                if( $result->_getStatusValue() == 'OK' ){
                    $logger->log(LogLevel::INFO , sprintf('Message sent to channel %s' , $event->getChannelTarget() ) , [ 'text' => $event->getText() ]);
                    $event->setDoneDateTime( $now );
                    $entityManager->persist( $event );
                    $entityManager->flush();
                    $eventsProcessed++;
                }

                $stashcatMediator->likeMessage( $result->getMessage() );
            }
        }
        $jsonResult['status'] = 'OK';
        $jsonResult['events'] = [
            'processed' => $eventsProcessed,
            'details' => $events
        ];
        return new JsonResponse( $jsonResult );
    }

}