<?php

namespace App\Controller;

use App\Core\DatabaseLogger;
use App\Entity\Event;
use App\Entity\LogEntry;
use App\Entity\User;
use App\Service\AppService;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LogLevel;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
class EventsController extends AbstractController
{
    #[Route('/events', name: 'events', methods: [ 'GET' ])]
    public function main( AppService $app , ManagerRegistry $doctrine , Request $request, #[CurrentUser] ?User $user ): Response
    {
        $manager = $doctrine->getManagerForClass( Event::class );

        $events = $manager->getRepository(Event::class )
            ->createQueryBuilder('entries')
            ->select('entries')
            ->orderBy('entries.dueDateTime' , 'DESC')
            ->getQuery()->getResult();

        if( ( $id = $request->get('id') ) === null ){
            $entity = new Event();
        } else if( ( $entity = $manager->getRepository( Event::class )->find( $id ) ) === null ){
            throw new \Exception('Entity not found!');
        }

        return $this->render('default/index.html.twig' , [
            'events' => $events,
            'date_format' => $app->getAppConfig()->getDateFormat(),
            'time_format' => $app->getAppConfig()->getTimeFormat(),
            'date_time_format' => $app->getAppConfig()->getDateFormat() . " - " . $app->getAppConfig()->getTimeFormat(),
            'current_entry' => $entity,
            'allowed_intervals' => $app->getAppConfig()->getAllowedIntervals(),
            'allowed_channels' => $app->getAppConfig()->getAllowedChannelNames()
        ]);
    }

    #[Route('/events/log', name: 'events_log', methods: [ 'GET' ])]
    public function eventsLog( AppService $app , ManagerRegistry $doctrine , Request $request, #[CurrentUser] ?User $user ): Response
    {
        $manager = $doctrine->getManagerForClass( LogEntry::class );

        $logs = $manager->getRepository(LogEntry::class )
            ->createQueryBuilder('log')
            ->select('log')
            ->orderBy('log.createdDateTime' , 'DESC')
            ->getQuery()->getResult();

        return $this->render('default/logs.html.twig' , [
            'logs' => $logs,
            'date_time_format' => $app->getAppConfig()->getDateFormat() . " - " . $app->getAppConfig()->getTimeFormat()
        ]);
    }

    #[Route('/events', name: 'save_event', methods: [ 'POST' ] )]
    public function saveEvent( Request $request , ManagerRegistry $doctrine , AppService $app, DatabaseLogger $logger ): RedirectResponse
    {
        $manager = $doctrine->getManagerForClass( Event::class );

        switch( $request->get('action') ){
            case 'save':
            case 'add' :

                    if( ( $id = $request->get('id') )  == null ){
                        $entity = new Event();
                    } else if( ( $entity = $manager->getRepository( Event::class )->find( $id ) ) === null ){
                        throw new \Exception('Entity not found!');
                    }

                    $now = new \DateTime();
                    $date = \DateTime::createFromFormat( $app->getAppConfig()->getDateFormat() . '-' . $app->getAppConfig()->getTimeFormat() , $request->get('date') . '-' . $request->get('time') );

                    $entity->setChannelTarget( $request->get('channel') );
                    $entity->setStartDateTime( $date );
                    $entity->setDueDateTime( $date );
                    $entity->setText( $request->get('text') );

                    if( ( $interval = $request->get('interval') ) !== null && !empty( $interval ) ){
                        if( ( $untilDate = $request->get('untilDate') ) !== null && !empty( $untilDate ) && ( $untilTime = $request->get('untilTime') ) !== null && !empty( $untilTime )  ){
                            $untilDate = \DateTime::createFromFormat( $app->getAppConfig()->getDateFormat() . '-' . $app->getAppConfig()->getTimeFormat() , $untilDate . '-' . $untilTime );
                            if( !in_array( $interval , array_keys( $app->getAppConfig()->getAllowedIntervals() ) ) ){
                                throw new \Exception('Interval not allowed!');
                            }
                            $entity->setUntilDateTime( $untilDate );
                        } else {
                            $entity->setUntilDateTime( null );
                        }
                        $interval = strval( $_POST['interval'] );
                        $entity->setDateInterval( $interval );
                    } else {
                        $entity->setUntilDateTime( null );
                        $entity->setDateInterval( null );
                        // Single processing event and new due date is in the future => redo it!
                        if( $entity->getDueDateTime() >= $now ){
                            $entity->setDoneDateTime( null );
                        }
                    }

                    $logger->log( LogLevel::NOTICE , sprintf('%s Event' , strtoupper( $request->get('action') )) , [
                        'text' => $entity->getText() ,
                        'interval' => $entity->getDateInterval() ,
                        'startDate' => $entity->getStartDateTime() ,
                        'untilDate' => $entity->getUntilDateTime()
                    ]);

                    $manager->persist( $entity );
                    $manager->flush();
                break;
            case 'delete' :
                if( ( $id = $request->get('id') )  !== null && ( $entity = $manager->getRepository( Event::class )->find( $id ) ) !== null ){
                    $manager->remove( $entity );
                    $manager->flush();
                }
                break;
            case 'edit' :
                if( ( $id = $request->get('id') ) !== null ){
                    return $this->redirectToRoute('events' , ['id' => $id ] );
                }
                break;
        }

        return $this->redirectToRoute('events');
    }

    #[Route('/events/execute', name: 'execute_events', methods: [ 'GET' ] )]
    public function executeEvent(){
        $response = $this->forward('App\Controller\JobController::main', [ 'context' => 'Manual' ]);
        return $this->render('default/events.execute.html.twig' , [
            'response' => $response,
        ]);
    }
}