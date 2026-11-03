<?php

namespace App\Controller;

use App\Command\SendMessage;
use App\Core\DatabaseLogger;
use App\Core\Logging\LogChannels;
use App\Core\Logging\LogFilter;
use App\Core\Logging\LogSearch;
use App\Core\MessageDecorator;
use App\Entity\Event;
use App\Entity\User;
use App\Kernel;
use App\Service\AppService;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LogLevel;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[IsGranted('ROLE_ADMIN')]
class EventsController extends AbstractController
{
    use CsrfTokenCheck;

    #[Route('/events', name: 'events', methods: [ 'GET' ])]
    public function main( AppService $app , Kernel $kernel , ManagerRegistry $doctrine , Request $request, #[CurrentUser] ?User $user ): Response
    {
        $manager = $doctrine->getManagerForClass( Event::class );

        $events = $manager->getRepository(Event::class )
            ->createQueryBuilder('entries')
            ->select('entries')
            ->orderBy('entries.dueDateTime' , \SortDirection::Ascending)
            ->getQuery()->getResult();

        if( ( $id = $request->query->get('id') ) === null ){
            $entity = new Event();
        } else if( ( $entity = $manager->getRepository( Event::class )->find( $id ) ) === null ){
            throw new \Exception('Entity not found!');
        }

        $decorator = new MessageDecorator( $kernel->getContainer() , [] );


        return $this->render('default/index.html.twig' , [
            'events' => $events,
            'date_format' => $app->getAppConfig()->getDateFormat(),
            'time_format' => $app->getAppConfig()->getTimeFormat(),
            'date_time_format' => $app->getAppConfig()->getDateFormat() . " - " . $app->getAppConfig()->getTimeFormat(),
            'current_entry' => $entity,
            'allowed_intervals' => $app->getAppConfig()->getAllowedIntervals(),
            'allowed_channels' => $app->getAppConfig()->getAllowedChannelNames(),
            'replacementKeys' => $decorator->getAvailableReplacements(),
            'conversation_channel_placeholder_name' => $app->getAppConfig()->getConversationChannelPlaceholderName()
        ]);
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
            'date_time_format' => $app->getAppConfig()->getDateFormat() . " - " . $app->getAppConfig()->getTimeFormat()
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

    #[Route('/events', name: 'save_event', methods: [ 'POST' ] )]
    public function saveEvent( Request $request , ManagerRegistry $doctrine , AppService $app, DatabaseLogger $logger, #[CurrentUser] ?User $user , Kernel $kernel ): RedirectResponse
    {
        $this->denyAccessUnlessCsrfTokenValid( 'events' , $request );

        $manager = $doctrine->getManagerForClass( Event::class );

        switch( $request->request->get('action') ){
            case 'save':
            case 'add' :

                    if( ( $id = $request->request->get('id') )  == null ){
                        $entity = new Event();
                    } else if( ( $entity = $manager->getRepository( Event::class )->find( $id ) ) === null ){
                        throw new \Exception('Entity not found!');
                    }

                    $now = new \DateTime();
                    $date = \DateTime::createFromFormat( $app->getAppConfig()->getDateFormat() . '-' . $app->getAppConfig()->getTimeFormat() , $request->request->get('date') . '-' . $request->request->get('time') );
                    if( $date === false ){
                        throw new BadRequestHttpException('Startdatum oder -uhrzeit ungültig!');
                    }

                    if( in_array( $request->request->get('channel') , $app->getAppConfig()->getAllowedChannelNames() ) ){
                        $entity->setChannelTarget( $request->request->get('channel') );
                    } else {
                        throw new \Exception('Channel-Name not allowed!');
                    }

                    $conversationId = trim( strval( $request->request->get('channel_payload_conversation_id') ) );
                    if( $entity->getChannelTarget() === $app->getAppConfig()->getConversationChannelPlaceholderName() && $conversationId === '' ){
                        throw new BadRequestHttpException('Bitte eine Unterhaltung auswählen oder eine ID eintragen!');
                    }
                    $entity->setConversationIdToPayload( $conversationId === '' ? null : $conversationId );
                    $entity->setStartDateTime( $date );

                    if( $request->request->get('resetDue' ) == 1 ){
                        $entity->setDueDateTime( $date );
                    }

                    $entity->setText( $request->request->get('text') );

                    if( ( $interval = $request->request->get('interval') ) !== null && !empty( $interval ) ){

                        if( !in_array( $interval , array_keys( $app->getAppConfig()->getAllowedIntervals() ) ) ){
                            throw new \Exception('Interval not allowed!');
                        }

                        if( ( $untilDate = $request->request->get('untilDate') ) !== null && !empty( $untilDate ) && ( $untilTime = $request->request->get('untilTime') ) !== null && !empty( $untilTime )  ){
                            $untilDate = \DateTime::createFromFormat( $app->getAppConfig()->getDateFormat() . '-' . $app->getAppConfig()->getTimeFormat() , $untilDate . '-' . $untilTime );
                            if( $untilDate === false ){
                                throw new BadRequestHttpException('Enddatum oder -uhrzeit ungültig!');
                            }
                            $entity->setUntilDateTime( $untilDate );
                        } else {
                            $entity->setUntilDateTime( null );
                        }
                        $entity->setDateInterval( strval( $interval ) );

                    } else {
                        $entity->setUntilDateTime( null );
                        $entity->setDateInterval( null );
                        // Single processing event and new due date is in the future => redo it!
                        if( $entity->getDueDateTime() >= $now ){
                            $entity->setDoneDateTime( null );
                        }
                    }

                    // Erneutes Speichern gibt ein nach Fehlversuchen gesperrtes Event wieder frei
                    $entity->resetFailedAttempts();

                    $logger->log( LogLevel::NOTICE , sprintf('%s Event' , strtoupper( $request->request->get('action') )) , [
                        'text' => $entity->getText() ,
                        'interval' => $entity->getDateInterval() ,
                        'startDate' => $entity->getStartDateTime() ,
                        'untilDate' => $entity->getUntilDateTime()
                    ]);

                    $manager->persist( $entity );
                    $manager->flush();
                break;
            case 'delete' :
                if( ( $id = $request->request->get('id') )  !== null && ( $entity = $manager->getRepository( Event::class )->find( $id ) ) !== null ){
                    $manager->remove( $entity );
                    $manager->flush();
                }
                break;
            case 'edit' :
                if( ( $id = $request->request->get('id') ) !== null ){
                    return $this->redirectToRoute('events' , ['id' => $id ] );
                }
                break;
            case 'trigger' :
                /* @var $entity Event */
                if( ( $id = $request->request->get('id') )  !== null && ( $entity = $manager->getRepository( Event::class )->find( $id ) ) !== null ){

                    $application = new Application( $kernel );
                    $application->setAutoExit(false);

                    $arguments = [
                        'command' => 'hermine:send-message',
                        '--context' => 'GUI-TRIGGER',
                        '--channel' => $entity->getChannelTarget(),
                        '--message' => $entity->getText()
                    ];

                    $channelPayload = $entity->getChannelTargetPayload();
                    if( isset( $channelPayload['conversationId'] ) ){
                        $arguments['--conversationId'] = $channelPayload['conversationId'];
                    }

                    $input = new ArrayInput( $arguments );

                    // You can use NullOutput() if you don't need the output
                    $output = new BufferedOutput();
                    if( $application->run($input, $output) === Command::SUCCESS ){

                        $entity->increaseTransmissionsCount();
                        $manager->persist( $entity );
                        $manager->flush();

                        $logger->info('Message Event triggered & processed' );
                    } else {
                        $logger->emergency('Message Event triggered & failed' , ['error' => $output->fetch()] );
                    }

                    return $this->redirectToRoute('events' );
                }
                break;
        }

        return $this->redirectToRoute('events');
    }

    #[Route('/events/execute', name: 'execute_events', methods: [ 'GET' ] )]
    public function executeEventForm( #[CurrentUser] ?User $user ): Response
    {
        return $this->render('default/events.execute.html.twig' , [
            'response' => null,
        ]);
    }

    #[Route('/events/execute', name: 'execute_events_run', methods: [ 'POST' ] )]
    public function executeEvent( Request $request , KernelInterface $kernel, #[CurrentUser] ?User $user ){
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

}