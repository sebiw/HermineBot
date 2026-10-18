<?php

// src/Command/CreateUserCommand.php
namespace App\Command;

use App\Core\DatabaseLogger;
use App\Core\MessageDecorator;
use App\Core\StashcatMediator;
use App\Entity\Event;
use App\Kernel;
use App\Service\AppService;
use App\Stashcat\Entities\Company;
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

        // Sperre gegen parallele Läufe (Cron jede Minute, manueller Start) – sonst würden fällige Events doppelt gesendet.
        // flock wird beim Prozessende automatisch freigegeben, auch nach einem Absturz.
        $lockFile = $this->getKernel()->getProjectDir() . '/var/daemon-tick.lock';
        ( new Filesystem() )->mkdir( dirname( $lockFile ) );
        if( ( $lockHandle = fopen( $lockFile , 'c' ) ) === false ){
            throw new \Exception(sprintf('Lock file could not be opened: %s' , $lockFile ));
        }

        if( !flock( $lockHandle , LOCK_EX | LOCK_NB ) ){
            fclose( $lockHandle );
            $this->getLogger()->log(LogLevel::WARNING , sprintf('%s: Event handler already running, skipped' , $context ) );
            $output->writeln( json_encode( [ 'status' => 'LOCKED' ] , JSON_PRETTY_PRINT ) );
            return Command::SUCCESS;
        }

        try {
            return $this->tick( $context , $input , $output );
        } finally {
            flock( $lockHandle , LOCK_UN );
            fclose( $lockHandle );
        }
    }

    /**
     * @param string $context
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws \Exception
     */
    protected function tick( string $context , InputInterface $input, OutputInterface $output ): int
    {

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
            // UND: das Event ist nicht nach zu vielen Fehlversuchen gesperrt
            ->andWhere('entries.failedAttempts < :maxFailedAttempts')
            ->setParameter('now' , $now  ) // Und das Fälligkeitsdatum liegt in der vergangenheit
            ->setParameter('maxFailedAttempts' , Event::MAX_FAILED_ATTEMPTS )
            ->getQuery()->getResult();

        $eventsProcessed = 0;
        if( count( $events ) > 0 ){
            // Mediator -> ease of access
            $stashcatMediator = $this->getAppService()->getMediator();
            $stashcatMediator->login();
            $stashcatMediator->decryptPrivateKey();
            $stashcatMediator->loadCompanies();

            $THWCompany = $stashcatMediator->getCompanyMembers()->getCompanyByName( $this->getAppService()->getAppConfig()->getStashcatEventCompany() );

            foreach( $events AS $event ){

                // Alles, was scheitern kann, VOR dem Senden erledigen – sonst würde eine bereits gesendete Nachricht beim nächsten Tick erneut verschickt
                try {
                    $nextDueDateTime = $event->calculateNextDueDateTime( $now );
                    $preparedMessage = $this->prepareMessage( $event , $stashcatMediator , $THWCompany );
                } catch ( \Throwable $e ){
                    $this->registerFailedAttempt( $event , $now , 'Preparing message failed' , [ 'error' => $e->getMessage() ] );
                    continue;
                }

                try {
                    $result = $preparedMessage['send']();
                } catch ( \Throwable $e ){
                    $this->registerFailedAttempt( $event , $now , 'Sending message failed' , [ 'error' => $e->getMessage() ] );
                    continue;
                }

                if( $result?->_getStatusValue() != 'OK' ){
                    $this->registerFailedAttempt( $event , $now , 'Sending message failed' , [ 'status' => $result?->_getStatusValue() ] );
                    continue;
                }

                // Gesendet -> sofort festhalten
                if( $nextDueDateTime !== null ){
                    $event->setDueDateTime( $nextDueDateTime );
                }
                $event->increaseTransmissionsCount();
                $event->resetFailedAttempts();
                $event->setDoneDateTime( $now );
                $entityManager->persist( $event );
                $entityManager->flush();
                $eventsProcessed++;

                $this->getLogger()->log(LogLevel::INFO , $preparedMessage['logMessage'] , [ 'text' => $preparedMessage['text'] ]);

                try {
                    $stashcatMediator->likeMessage( $result->getMessage() );
                } catch ( \Throwable $e ){
                    $this->getLogger()->log(LogLevel::WARNING , sprintf('Liking message failed for Event #%s' , $event->getId() ) , [ 'error' => $e->getMessage() ]);
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
     * Lädt das Ziel (Channel oder Unterhaltung) und ersetzt die Platzhalter, ohne zu senden.
     * @param Event $event
     * @param StashcatMediator $stashcatMediator
     * @param Company $THWCompany
     * @return array [ 'text' => string, 'logMessage' => string, 'send' => callable ]
     * @throws \Exception
     */
    protected function prepareMessage( Event $event , StashcatMediator $stashcatMediator , Company $THWCompany ): array
    {
        // Freigabeliste auch hier prüfen – Events können vor einer Einschränkung oder direkt in der DB angelegt worden sein
        if( !in_array( $event->getChannelTarget() , $this->getAppService()->getAppConfig()->getAllowedChannelNames() ) ){
            throw new \Exception(sprintf("Channel not allowed: %s" , $event->getChannelTarget() ));
        }

        $eventText = $event->getText();
        $autoAppend = $this->getAppService()->getAppConfig()->getAutoAppendToMessages();

        // Conversation ???
        if( $event->getChannelTarget() === $this->getAppService()->getAppConfig()->getConversationChannelPlaceholderName() ){

            $stashcatMediator->loadConversations();

            if( ( $conversationId = $event->getConversationIdFromPayload() ) === null ){
                throw new \Exception('Conversation ID missing!');
            }

            if( ( $conversation = $stashcatMediator->getConversationById( $conversationId ) ) === null ){
                throw new \Exception(sprintf("Conversation not found by ID #%s" , $conversationId ));
            }

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

            return [
                'text' => $eventText,
                'logMessage' => sprintf('Message sent to conversation %s, members: %s' , $conversation->getId() , implode(', ' , $memberNames ) ),
                'send' => fn() => $stashcatMediator->sendMessageToConversation( $eventText . $autoAppend , $conversation )
            ];
        }

        // Channel-Message ???
        $stashcatMediator->loadChannelSubscripted( $THWCompany );

        if( ( $THWChannel = $stashcatMediator->getChannelOfCompany( $event->getChannelTarget() , $THWCompany ) ) === null ){
            throw new \Exception(sprintf("Channel not found: %s" , $event->getChannelTarget() ));
        }

        $stashcatMediator->decryptChannelPrivateKey( $THWChannel );

        // Decorator....
        $messageDecorator = new MessageDecorator( $this->getKernel()->getContainer() , [
            $THWCompany,
            $THWChannel,
            $this->getAppService(),
            $event
        ]);

        $eventText = $messageDecorator->replace( $eventText );

        return [
            'text' => $eventText,
            'logMessage' => sprintf('Message sent to channel %s' , $event->getChannelTarget() ),
            'send' => fn() => $stashcatMediator->sendMessageToChannel( $eventText . $autoAppend , $THWChannel )
        ];
    }

    /**
     * Zählt einen Fehlversuch. Nach Event::MAX_FAILED_ATTEMPTS wird ein wiederkehrendes Event beim nächsten Termin fortgesetzt,
     * ein einmaliges (oder eines mit ungültigem Intervall) bleibt bis zum erneuten Speichern gesperrt.
     * @param Event $event
     * @param DateTime $now
     * @param string $reason
     * @param array $context
     * @return void
     */
    protected function registerFailedAttempt( Event $event , DateTime $now , string $reason , array $context = [] ): void
    {
        $event->increaseFailedAttempts();
        $context['attempt'] = sprintf('%s/%s' , $event->getFailedAttempts() , Event::MAX_FAILED_ATTEMPTS );

        if( !$event->eventFailed() ){
            $this->getLogger()->log(LogLevel::ERROR , sprintf('%s for Event #%s, retrying with next tick' , $reason , $event->getId() ) , $context );
        } else {
            try {
                $nextDueDateTime = $event->calculateNextDueDateTime( $now );
            } catch ( \Throwable $e ){
                $nextDueDateTime = null;
            }

            if( $nextDueDateTime !== null ){
                $event->setDueDateTime( $nextDueDateTime );
                $event->resetFailedAttempts();
                $this->getLogger()->log(LogLevel::CRITICAL , sprintf('%s for Event #%s, skipped until next due date' , $reason , $event->getId() ) , $context );
            } else {
                $this->getLogger()->log(LogLevel::CRITICAL , sprintf('%s for Event #%s, event disabled until saved again' , $reason , $event->getId() ) , $context );
            }
        }

        $entityManager = $this->getDoctrine()->getManagerForClass( Event::class );
        $entityManager->persist( $event );
        $entityManager->flush();
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
            $sent = false;
            if( isset( $message['message'] , $message['channel'] , $message['context'] ) ){
                try {
                    $command = $this->getApplication()->find('hermine:send-message');
                    $input = new ArrayInput([
                        '--context' => $message['context'],
                        '--channel' => $message['channel'],
                        '--message' => $message['message']
                    ]);
                    if( $command->run( $input , $output ) === self::SUCCESS ){
                        $this->getLogger()->log(LogLevel::INFO , 'hermine:send-message executed!');
                        $sent = true;
                    }
                } catch ( \Throwable $e ){
                    // Darf den Tick (und damit die Events) nicht abbrechen
                    $this->getLogger()->log(LogLevel::ERROR , 'Sending message file failed' , [ 'file' => $file->getRealPath() , 'error' => $e->getMessage() ]);
                }
            } else {
                $this->getLogger()->log(LogLevel::ERROR , 'Message malformed!' , [ 'keys_exist' => is_array( $message ) ? array_keys( $message ) : null ]);
            }

            if( $sent ){
                $fileSystem->remove( $file->getRealPath() );
            } else {
                // Nicht löschen, sondern beiseitelegen (wird wegen der Endung nicht erneut verarbeitet)
                $fileSystem->rename( $file->getRealPath() , $file->getRealPath() . '.failed' , true );
                $this->getLogger()->log(LogLevel::CRITICAL , 'Message file not sent, kept as .failed' , [ 'file' => $file->getRealPath() . '.failed' ]);
            }
        }
    }

}