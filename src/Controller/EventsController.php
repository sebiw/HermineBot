<?php

namespace App\Controller;

use App\Command\SendMessage;
use App\Core\DatabaseLogger;
use App\Core\MessageDecorator;
use App\Entity\Event;
use App\Entity\LogEntry;
use App\Entity\User;
use App\Kernel;
use App\Service\AppService;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LogLevel;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[IsGranted('ROLE_ADMIN')]
class EventsController extends AbstractController
{
    #[Route('/events', name: 'events', methods: [ 'GET' ])]
    public function main( AppService $app , Kernel $kernel , ManagerRegistry $doctrine , Request $request, #[CurrentUser] ?User $user ): Response
    {
        $manager = $doctrine->getManagerForClass( Event::class );

        $events = $manager->getRepository(Event::class )
            ->createQueryBuilder('entries')
            ->select('entries')
            ->orderBy('entries.dueDateTime' , 'ASC')
            ->getQuery()->getResult();

        if( ( $id = $request->get('id') ) === null ){
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
    public function eventsLog( AppService $app , ManagerRegistry $doctrine , Request $request, #[CurrentUser] ?User $user ): Response
    {
        $manager = $doctrine->getManagerForClass( LogEntry::class );

        $itemsPerPage = 100;
        $currentPage = intval( $request->get('page' , 1 ) ) ;
        $offset = ( $currentPage - 1 ) * $itemsPerPage;

        $logsQueryBuilder = $manager->getRepository(LogEntry::class )
            ->createQueryBuilder('log')
            ->select('log')
            ->orderBy('log.createdDateTime' , 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($itemsPerPage);

        $paginator = new Paginator( $logsQueryBuilder );
        $maxPages = ceil($paginator->count() / $itemsPerPage );
        return $this->render('default/logs.html.twig' , [
            'logs' => $paginator,
            'currentPage' => $currentPage,
            'maxPages' => $maxPages,
            'date_time_format' => $app->getAppConfig()->getDateFormat() . " - " . $app->getAppConfig()->getTimeFormat()
        ]);
    }

    #[Route('/events/log/truncate', name: 'events_log_truncate', methods: [ 'GET' ])]
    public function truncateLogs( AppService $app, ManagerRegistry $doctrine ,DatabaseLogger $logger, #[CurrentUser] ?User $user ){
        $manager = $doctrine->getManagerForClass( LogEntry::class );
        $deleteFromDateTime = (new \DateTime())->modify('-1 weeks');
        $manager->getRepository(LogEntry::class )
            ->createQueryBuilder('log')
            ->delete(LogEntry::class , 'log')
            ->where('log.createdDateTime < :deleteFrom')
            ->setParameter('deleteFrom' , $deleteFromDateTime )
            ->getQuery()->getResult();

        $logger->info(sprintf(/** @lang text */ 'Delete log entries starting from %s' , $deleteFromDateTime->format($app->getAppConfig()->getDateFormat() . " - " . $app->getAppConfig()->getTimeFormat())));
        return $this->redirectToRoute('events_log');
    }

    #[Route('/events', name: 'save_event', methods: [ 'POST' ] )]
    public function saveEvent( Request $request , ManagerRegistry $doctrine , AppService $app, DatabaseLogger $logger, #[CurrentUser] ?User $user , Kernel $kernel ): RedirectResponse
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

                    if( in_array( $request->get('channel') , $app->getAppConfig()->getAllowedChannelNames() ) ){
                        $entity->setChannelTarget( $request->get('channel') );
                    } else {
                        throw new \Exception('Channel-Name not allowed!');
                    }

                    $entity->setConversationIdToPayload( $request->get('channel_payload_conversation_id') );
                    $entity->setStartDateTime( $date );

                    if( $request->get('resetDue' ) == 1 ){
                        $entity->setDueDateTime( $date );
                    }

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
            case 'trigger' :
                /* @var $entity Event */
                if( ( $id = $request->get('id') )  !== null && ( $entity = $manager->getRepository( Event::class )->find( $id ) ) !== null ){

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
    public function executeEvent( KernelInterface $kernel, #[CurrentUser] ?User $user ){

        $application = new Application($kernel);
        $application->setAutoExit(false);

        $input = new ArrayInput([
            'command' => 'daemon:tick',
            '--context' => 'Manual'
        ]);

        // You can use NullOutput() if you don't need the output
        $output = new BufferedOutput();
        $application->run($input, $output);

        return $this->render('default/events.execute.html.twig' , [
            'response' => $output->fetch(),
        ]);
    }

    // @TODO: Mail to Hermine?
    // Materialanforderungen / Materialeingang automatisch in Hermine Posten?
    // Tracking von Material: Was für Anforderungen wurden erfasst / Welche sind noch offen?

    // @TODO: iCal to Hermine?
    // Trigger events by Kalender entries

    // @TODO: Hermine Bot?
    // Keywords trigger messages? => Kombination mit "Question Bot"

    // @TODO: Question Bot?
    // Umfragen / Abfragen vereinfachen: Rückmeldungen Lesen unter Bereitstellen um Übersichten zu planen.
    // Kombinieren mit der Helferdislozierungsliste aus dem THWin könnte man Excel-Dateien in Software gießen.
    // Antwort-Optionen durch Emojies? (JA / NEIN)
    // Mehrere Termine: unterschiedliche Emojies / Keywords?
    // => DENNOCH Manuelles Editieren muss möglich sein!!!
    // PRIVATE Nachrichten entschlüsseln?

}