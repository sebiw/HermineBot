<?php

// src/Command/CreateUserCommand.php
namespace App\Command;

use App\Core\DatabaseLogger;
use App\Entity\Event;
use App\Entity\File;
use App\Entity\File_Status;
use App\Entity\Material_Message;
use App\Kernel;
use App\Service\AppService;
use DateInterval;
use DateTime;
use Doctrine\Persistence\ManagerRegistry;
use PhpImap\Mailbox;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

// the name of the command is what users type after "php bin/console"
#[AsCommand(name: 'daemon:tick', description: 'One Tick for the Background Daemon')]
class Daemon extends Command
{

    private ManagerRegistry $doctrine;
    private DatabaseLogger $logger;
    private Kernel $kernel;
    private AppService $appService;

    /**
     * @param AppService $app
     * @param ManagerRegistry $doctrine
     * @param DatabaseLogger $logger
     * @param Kernel $kernel
     * @param string|null $name
     */
    public function __construct(  AppService $app , ManagerRegistry $doctrine , #[Autowire(service: 'logger.events')] DatabaseLogger $logger , Kernel $kernel , string $name = null)
    {
        parent::__construct($name);
        $this->doctrine = $doctrine;
        $this->logger = $logger;
        $this->kernel = $kernel;
        $this->appService = $app;
    }

    /**
     * @return void
     */
    protected function configure()
    {
        parent::configure();
        $this->addOption('context' , '-x' , InputOption::VALUE_OPTIONAL , 'Context of this Call' , 'Console' );
    }

    /**
     * @return AppService
     */
    protected function getAppService(): AppService
    {
        return $this->appService;
    }

    /**
     * @return ManagerRegistry
     */
    protected function getDoctrine(): ManagerRegistry
    {
        return $this->doctrine;
    }

    /**
     * @return DatabaseLogger
     */
    protected function getLogger(): DatabaseLogger
    {
        return $this->logger;
    }

    /**
     * @return Kernel
     */
    protected function getKernel(): Kernel
    {
        return $this->kernel;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output ): int
    {

        $context = $input->getOption('context');
        if( empty( $context ) ){
            $context = 'Console';
        }

        $this->getLogger()->log(LogLevel::INFO , sprintf('%s: Started event handler' , $context ) );

        $jsonResult = [];
        $now = new DateTime();
        $entityManager = $this->getDoctrine()->getManagerForClass( Event::class );

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
            $stashcatMediator = $this->getAppService()->getMediator();
            $stashcatMediator->login();
            $stashcatMediator->decryptPrivateKey();
            $stashcatMediator->loadCompanies();

            $THWCompany = $stashcatMediator->getCompanyMembers()->getCompanyByName('THW');
            $stashcatMediator->loadChannelSubscripted( $THWCompany );

            foreach( $events AS $event ){

                $THWChannel = $stashcatMediator->getChannelOfCompany( $event->getChannelTarget() , $THWCompany );
                $stashcatMediator->decryptChannelPrivateKey( $THWChannel );

                $eventText = $event->getText();
                $allowedIntervals = $this->getAppService()->getAppConfig()->getAllowedIntervals();

                // Replacements...
                $replacements = [
                    '{{CompanyActiveUser}}' => $THWCompany->getActiveUser(),
                    '{{CompanyCreatedUser}}' => $THWCompany->getCreatedUser(),
                    '{{CompanyName}}' => $THWCompany->getName(),
                    '{{ChannelUserCount}}' => $THWChannel->getUserCount(),
                    '{{EventInterval}}' => !empty( $event->getDateInterval() ) ? ( $allowedIntervals[ $event->getDateInterval() ] ?? '#N/A' ) : ''
                ];
                $eventText = str_replace( array_keys( $replacements ) , array_values( $replacements ) , $eventText );

                // Prepare Group Stats if required...
                $groupStatsKey = '{{CompanyGroupsStats}}';
                if( str_contains( $eventText , $groupStatsKey ) ){
                    $stashcatMediator->loadGroups( $THWCompany );
                    $THWGroups = $stashcatMediator->getGroupsOfCompany( $THWCompany );
                    $groupStatistics = [];
                    foreach( $THWGroups AS $group ){
                        $groupStatistics[ $group->getPossibleUser() ] = sprintf('%s: %s mögliche Nutzer' , $group->getName() , $group->getPossibleUser() );
                    }
                    ksort( $groupStatistics );
                    $eventText = str_replace( $groupStatsKey , implode( "\r\n" , $groupStatistics ) , $eventText );
                }

                // Send message!
                $result = $stashcatMediator->sendMessageToChannel( $eventText . $this->getAppService()->getAppConfig()->getAutoAppendToMessages() , $THWChannel );

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
                    $this->getLogger()->log(LogLevel::INFO , sprintf('Message sent to channel %s' , $event->getChannelTarget() ) , [ 'text' => $eventText ]);
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

        $output->writeln( json_encode( $jsonResult , JSON_PRETTY_PRINT ) );
        return Command::SUCCESS;
    }

}