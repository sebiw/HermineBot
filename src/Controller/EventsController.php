<?php

namespace App\Controller;

use App\Core\DatabaseLogger;
use App\Core\Events\EventForm;
use App\Core\Logging\LogChannels;
use App\Core\Logging\LogFilter;
use App\Core\Logging\LogSearch;
use App\Core\MessageDecorator;
use App\Entity\Event;
use App\Entity\User;
use App\Kernel;
use App\Service\AppService;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Psr\Log\LogLevel;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[IsGranted('ROLE_ADMIN')]
class EventsController extends AbstractController
{
    use CsrfTokenCheck;

    #[Route('/events', name: 'events', methods: [ 'GET' ])]
    public function main( AppService $app , Kernel $kernel , ManagerRegistry $doctrine ): Response
    {
        /** @var Event[] $events */
        $events = self::manager( $doctrine )->getRepository( Event::class )->findAll();
        // Offene nach nächster Übermittlung, Erledigte danach (zuletzt gesendete zuerst)
        usort( $events , function( Event $a , Event $b ){
            $doneA = $a->getState() === Event::STATE_DONE;
            $doneB = $b->getState() === Event::STATE_DONE;
            if( $doneA !== $doneB ){
                return $doneA <=> $doneB;
            }
            return $doneA ? ( $b->getDoneDateTime() <=> $a->getDoneDateTime() ) : ( $a->getDueDateTime() <=> $b->getDueDateTime() );
        } );
        return $this->render('default/events.html.twig' , [
            'events' => $events,
            'labels' => $this->placeholderLabels( $kernel ),
            'allowed_intervals' => $app->getAppConfig()->getAllowedIntervals(),
            'conversation_placeholder' => $app->getAppConfig()->getConversationChannelPlaceholderName(),
            'now' => new \DateTime(),
        ]);
    }

    #[Route('/events/new', name: 'events_new', methods: [ 'GET' ])]
    public function newEvent( AppService $app , Kernel $kernel ): Response
    {
        // Vorschlag: nächste volle Stunde
        $start = new \DateTime( '+1 hour' );
        $start->setTime( (int) $start->format( 'H' ) , 0 );
        return $this->renderForm( new Event() , new EventForm( start: $start->format( EventForm::DATE_TIME_FORMAT ) ) , [] , $app , $kernel );
    }

    #[Route('/events/new', name: 'events_create', methods: [ 'POST' ])]
    public function createEvent( Request $request , ManagerRegistry $doctrine , AppService $app , Kernel $kernel , DatabaseLogger $logger ): Response
    {
        $this->denyAccessUnlessCsrfTokenValid( 'events' , $request );
        return $this->saveForm( new Event() , $request , $doctrine , $app , $kernel , $logger );
    }

    #[Route('/events/{id}', name: 'events_edit', requirements: [ 'id' => '\d+' ], methods: [ 'GET' ])]
    public function editEvent( int $id , ManagerRegistry $doctrine , AppService $app , Kernel $kernel ): Response
    {
        $event = $this->findEvent( $id , $doctrine );
        return $this->renderForm( $event , EventForm::fromEvent( $event ) , [] , $app , $kernel );
    }

    #[Route('/events/{id}', name: 'events_save', requirements: [ 'id' => '\d+' ], methods: [ 'POST' ])]
    public function saveEvent( int $id , Request $request , ManagerRegistry $doctrine , AppService $app , Kernel $kernel , DatabaseLogger $logger ): Response
    {
        $this->denyAccessUnlessCsrfTokenValid( 'events' , $request );
        return $this->saveForm( $this->findEvent( $id , $doctrine ) , $request , $doctrine , $app , $kernel , $logger );
    }

    #[Route('/events/{id}/delete', name: 'events_delete', requirements: [ 'id' => '\d+' ], methods: [ 'POST' ])]
    public function deleteEvent( int $id , Request $request , ManagerRegistry $doctrine , DatabaseLogger $logger ): Response
    {
        $this->denyAccessUnlessCsrfTokenValid( 'events' , $request );
        $event = $this->findEvent( $id , $doctrine );
        $manager = self::manager( $doctrine );
        $logger->log( LogLevel::NOTICE , sprintf( 'DELETE Event #%d' , $id ) , [ 'channel' => $event->getChannelTarget() , 'text' => $event->getText() ] );
        $manager->remove( $event );
        $manager->flush();
        $this->addFlash( 'success' , 'Event gelöscht.' );
        return $this->redirectToRoute('events');
    }

    /**
     * Sendet das Event einmal sofort (mit Platzhaltern), ändert den Plan nicht
     */
    #[Route('/events/{id}/send', name: 'events_send', requirements: [ 'id' => '\d+' ], methods: [ 'POST' ])]
    public function sendEvent( int $id , Request $request , ManagerRegistry $doctrine , Kernel $kernel , DatabaseLogger $logger ): Response
    {
        $this->denyAccessUnlessCsrfTokenValid( 'events' , $request );
        $event = $this->findEvent( $id , $doctrine );

        $application = new Application( $kernel );
        $application->setAutoExit( false );
        $arguments = [
            'command' => 'hermine:send-message',
            '--context' => 'GUI-TRIGGER',
            '--channel' => $event->getChannelTarget(),
            '--message' => $event->getText(),
        ];
        if( ( $conversationId = $event->getConversationIdFromPayload() ) !== null ){
            $arguments['--conversationId'] = $conversationId;
        }
        $output = new BufferedOutput();
        if( $application->run( new ArrayInput( $arguments ) , $output ) === Command::SUCCESS ){
            $event->increaseTransmissionsCount();
            self::manager( $doctrine )->flush();
            $logger->info( sprintf( 'Message Event #%d triggered & processed' , $id ) );
            $this->addFlash( 'success' , 'Nachricht gesendet.' );
        } else {
            $logger->emergency( sprintf( 'Message Event #%d triggered & failed' , $id ) , [ 'error' => $output->fetch() ] );
            $this->addFlash( 'error' , 'Senden fehlgeschlagen, Einzelheiten unter Logs.' );
        }
        return $this->redirectToRoute('events');
    }

    private function findEvent( int $id , ManagerRegistry $doctrine ): Event
    {
        $event = self::manager( $doctrine )->getRepository( Event::class )->find( $id );
        if( !$event instanceof Event ){
            throw $this->createNotFoundException( 'Event nicht gefunden.' );
        }
        return $event;
    }

    private function saveForm( Event $event , Request $request , ManagerRegistry $doctrine , AppService $app , Kernel $kernel , DatabaseLogger $logger ): Response
    {
        $isNew = $event->getId() === null;
        $form = EventForm::fromInput( $request->request->all() );
        $errors = $form->applyTo( $event , $app->getAppConfig() , new \DateTime() );
        if( $errors !== [] ){
            // Eingaben bleiben stehen, nichts gespeichert
            return $this->renderForm( $event , $form , $errors , $app , $kernel , Response::HTTP_UNPROCESSABLE_ENTITY );
        }
        $manager = self::manager( $doctrine );
        $manager->persist( $event );
        $manager->flush();
        $logger->log( LogLevel::NOTICE , sprintf( '%s Event #%d' , $isNew ? 'ADD' : 'SAVE' , $event->getId() ) , [
            'channel' => $event->getChannelTarget(),
            'text' => $event->getText(),
            'interval' => $event->getDateInterval(),
            'startDate' => $event->getStartDateTime(),
            'untilDate' => $event->getUntilDateTime(),
        ]);
        $this->addFlash( 'success' , $isNew ? 'Event angelegt.' : 'Event gespeichert.' );
        return $this->redirectToRoute('events');
    }

    /**
     * @param list<string> $errors
     */
    private function renderForm( Event $event , EventForm $form , array $errors , AppService $app , Kernel $kernel , int $status = Response::HTTP_OK ): Response
    {
        $config = $app->getAppConfig();
        if( $errors !== [] ){
            // Meldung oben rechts wie im StAN-Manager, die Eingaben bleiben im Formular
            $this->addFlash( 'error' , implode( ' ' , $errors ) );
        }
        $placeholders = ( new MessageDecorator( $kernel->getContainer() , [] ) )->describeReplacements();
        $groups = [];
        foreach( $placeholders AS $placeholder ){
            $groups[ $placeholder['group'] ][] = $placeholder;
        }
        return $this->render('default/event.edit.html.twig' , [
            'event' => $event,
            'form' => $form,
            'allowed_intervals' => $config->getAllowedIntervals(),
            'channels' => array_values( array_diff( $config->getAllowedChannelNames() , [ $config->getConversationChannelPlaceholderName() ] ) ),
            'conversation_placeholder' => $config->getConversationChannelPlaceholderName(),
            'placeholder_groups' => $groups,
            'placeholder_labels' => array_column( $placeholders , 'label' , 'code' ),
            'auto_append' => $config->getAutoAppendToMessages(),
        ] , new Response( null , $status ) );
    }

    /**
     * @return array<string, string> Platzhalter-Syntax => Name
     */
    private function placeholderLabels( Kernel $kernel ): array
    {
        return array_column( ( new MessageDecorator( $kernel->getContainer() , [] ) )->describeReplacements() , 'label' , 'code' );
    }

    #[Route('/events/log', name: 'events_log', methods: [ 'GET' ])]
    public function eventsLog( AppService $app , Request $request , LogSearch $search ): Response
    {
        $filter = LogFilter::fromQuery( $request->query );
        $result = $search->search( $filter );
        // Bekannte Bereiche in fester Reihenfolge, dazu was sonst noch im Log steht
        $channels = array_unique( array_merge( array_keys( LogChannels::LABELS ) , $search->channels() , $filter->channel === '' ? [] : [ $filter->channel ] ) );
        return $this->render('default/logs.html.twig' , [
            'logs' => $result['rows'],
            'total' => $result['total'],
            'currentPage' => $result['page'],
            'maxPages' => $result['pages'],
            'filter' => $filter,
            'query' => $filter->toQuery(),
            'channels' => array_combine( $channels , array_map( [ LogChannels::class , 'label' ] , $channels ) ),
            'levels' => LogFilter::LEVEL_LABELS,
            'date_time_format' => $app->getAppConfig()->getDateFormat() . " " . $app->getAppConfig()->getTimeFormat()
        ]);
    }

    /**
     * Offene (nicht archivierte) Unterhaltungen für die Auswahl im Formular – wird per AJAX geladen,
     * damit /events auch ohne Erreichbarkeit von Hermine funktioniert. Nur lesend.
     */
    #[Route('/events/conversations', name: 'events_conversations', methods: [ 'GET' ])]
    public function conversations( AppService $app , DatabaseLogger $logger ): JsonResponse
    {
        try {
            $stashcatMediator = $app->getMediator();
            if( !$stashcatMediator->login() ){
                throw new \Exception('Login failed!');
            }
            $stashcatMediator->loadConversations();

            $conversations = [];
            foreach( $stashcatMediator->getLoadedConversations() AS $conversation ){
                // Eine fehlerhafte Unterhaltung soll nicht die ganze Liste verhindern
                try {
                    $label = $stashcatMediator->getConversationLabel( $conversation );
                } catch( \Throwable $e ){
                    $logger->warning('Conversation label failed' , [ 'id' => $conversation->getId() , 'error' => $e->getMessage() ] );
                    $label = '(Mitglieder nicht lesbar)';
                }
                $conversations[] = [
                    'id' => strval( $conversation->getId() ),
                    'label' => $label
                ];
            }
            return $this->json( [ 'status' => 'OK' , 'conversations' => $conversations ] );
        } catch( \Throwable $e ){
            $logger->error('Loading conversations failed' , [ 'error' => $e->getMessage() ] );
            // Nur für Admins sichtbar; Meldungen nennen keine Werte aus Hermine-Antworten
            return $this->json( [ 'status' => 'ERROR' , 'message' => $e->getMessage() ] , Response::HTTP_INTERNAL_SERVER_ERROR );
        }
    }

    #[Route('/events/execute', name: 'execute_events', methods: [ 'GET' ] )]
    public function executeEventForm( #[CurrentUser] ?User $user ): Response
    {
        return $this->render('default/events.execute.html.twig' , [
            'response' => null,
        ]);
    }

    #[Route('/events/execute', name: 'execute_events_run', methods: [ 'POST' ] )]
    public function executeEvent( Request $request , KernelInterface $kernel, #[CurrentUser] ?User $user ): Response {
        // Sendet echte Nachrichten -> nur per POST mit Token (Schutz vor CSRF über Links/Bilder)
        $this->denyAccessUnlessCsrfTokenValid( 'execute_events' , $request );

        $application = new Application($kernel);
        $application->setAutoExit(false);

        $input = new ArrayInput([
            'command' => 'daemon:tick',
            '--context' => 'Manual',
            '--without-jobs' => true,
        ]);

        // You can use NullOutput() if you don't need the output
        $output = new BufferedOutput();
        $application->run($input, $output);

        return $this->render('default/events.execute.html.twig' , [
            'response' => $output->fetch(),
        ]);
    }

    private static function manager( ManagerRegistry $doctrine ): ObjectManager
    {
        return $doctrine->getManagerForClass( Event::class ) ?? throw new \LogicException( 'Kein Doctrine-Manager für Event konfiguriert' );
    }

}
