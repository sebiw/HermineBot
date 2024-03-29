<?php

// src/Command/CreateUserCommand.php
namespace App\Command;

use App\Core\DatabaseLogger;
use App\Core\MessageDecorator;
use App\Entity\Event;
use App\Kernel;
use App\Service\AppService;
use DateInterval;
use DateTime;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Messenger\MessageBusInterface;

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
    public function __construct(  AppService $app , ManagerRegistry $doctrine , #[Autowire(service: 'logger.events')] DatabaseLogger $logger, Kernel $kernel , string $name = null)
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

        $this->checkMessageFiles( $input , $output );

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

            $allowedIntervals = $this->getAppService()->getAppConfig()->getAllowedIntervals();

            foreach( $events AS $event ){

                $eventText = $event->getText();
                // Analyze Event -> Pull Requirements / Check Requirements => If all Requirements exist => Process!
                // If not, skip processing this event, fire Events to get Requirements. => DateTime Check -> Request <-> Response | if to old, fire new Request

                $result = $messageSentLogMessage = null;

                // Conversation ???
                if( $event->getChannelTarget() === $this->getAppService()->getAppConfig()->getConversationChannelPlaceholderName() ){

                    $stashcatMediator->loadConversations();

                    $channelPayload = $event->getChannelTargetPayload();
                    if( isset( $channelPayload['conversationId'] ) ){

                        if( ( $conversation = $stashcatMediator->getConversationById( $channelPayload['conversationId'] ) ) === null ){
                            $this->getLogger()->log(LogLevel::CRITICAL , sprintf("Conversation not found by ID #%s" , $channelPayload['conversationId'] ) , [ 'text' => $eventText ]);
                        }
                        else
                        {
                            $stashcatMediator->decryptConversationPrivateKey( $conversation );

                            // Decorator....
                            $messageDecorator = new MessageDecorator( $this->getKernel()->getContainer() , [
                                $THWCompany,
                                $this->getAppService(),
                                $event
                            ]);

                            $eventText = $messageDecorator->replace( $eventText );

                            $memberNames = [];
                            foreach( $conversation->getMembers() AS $member ){
                                $memberNames[] = $member->getCompleteName();
                            }

                            $result = $stashcatMediator->sendMessageToConversation( $eventText . $this->getAppService()->getAppConfig()->getAutoAppendToMessages() , $conversation );

                            $messageSentLogMessage = sprintf('Message sent to conversation %s, members: %s' , $conversation->getId() , implode(', ' , $memberNames ) );

                        }
                    }

                }
                // Channel-Message ???
                else
                {

                    $stashcatMediator->loadChannelSubscripted( $THWCompany );

                    $THWChannel = $stashcatMediator->getChannelOfCompany( $event->getChannelTarget() , $THWCompany );
                    $stashcatMediator->decryptChannelPrivateKey( $THWChannel );

                    // Decorator....
                    $messageDecorator = new MessageDecorator( $this->getKernel()->getContainer() , [
                        $THWCompany,
                        $THWChannel,
                        $this->getAppService(),
                        $event
                    ]);

                    $eventText = $messageDecorator->replace( $eventText );

                    $result = $stashcatMediator->sendMessageToChannel( $eventText . $this->getAppService()->getAppConfig()->getAutoAppendToMessages() , $THWChannel );
                    $messageSentLogMessage = sprintf('Message sent to channel %s' , $event->getChannelTarget() );

                }


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

                if( $result?->_getStatusValue() == 'OK' ){

                    if( $messageSentLogMessage !== null ){
                        $this->getLogger()->log(LogLevel::INFO , $messageSentLogMessage , [ 'text' => $eventText ]);
                    }

                    $event->setDoneDateTime( $now );
                    $entityManager->persist( $event );
                    $entityManager->flush();
                    $eventsProcessed++;

                    $stashcatMediator->likeMessage( $result->getMessage() );
                }
                else
                {
                    $this->getLogger()->log(LogLevel::CRITICAL , sprintf("Sending message failed for Event #%s" , $event->getId() ) , [ 'status' => $result?->_getStatusValue() ]);
                }

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

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     * @throws \Symfony\Component\Console\Exception\ExceptionInterface
     */
    protected function checkMessageFiles( InputInterface $input, OutputInterface $output  ): void
    {
        $messageFilesIn = trim( $this->getKernel()->getContainer()->getParameter('app.message_files_in') ?? '' );
        if( empty( $messageFilesIn ) ){
            return;
        }
        $messageInDir = BASE_PATH . $messageFilesIn;
        $fileSystem = new Filesystem();
        $fileSystem->mkdir( $messageInDir );
        $finder = new Finder();
        $finder->files()->in( $messageInDir )->name('*.json');
        foreach( $finder AS $file ){
            $this->getLogger()->log(LogLevel::INFO , 'Processing message file...' , [ 'file' => $file->getRealPath() ]);
            $message = json_decode( $file->getContents() , true );
            if( isset( $message['message'] , $message['channel'] , $message['context'] ) ){
                $command = $this->getApplication()->find('hermine:send-message');
                $input = new ArrayInput([
                    '--context' => $message['context'],
                    '--channel' => $message['channel'],
                    '--message' => $message['message']
                ]);
                if( $command->run( $input , $output ) === self::SUCCESS ){
                    $this->getLogger()->log(LogLevel::INFO , 'hermine:send-message executed!');
                }
            } else {
                $this->getLogger()->log(LogLevel::INFO , 'Message malformed!' , [ 'keys_exist' => is_array( $message ) ? array_keys( $message ) : null ]);
            }
            $fileSystem->remove( $file->getRealPath() );
        }
    }

}