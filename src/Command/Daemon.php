<?php

// src/Command/CreateUserCommand.php
namespace App\Command;

use App\Core\DatabaseLogger;
use App\Core\FileLock;
use App\Core\Job\JobScheduler;
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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

// the name of the command is what users type after "php bin/console"
#[AsCommand(name: 'daemon:tick', description: 'One Tick for the Background Daemon')]
class Daemon extends Command
{

    private ManagerRegistry $doctrine;
    private DatabaseLogger $logger;
    private Kernel $kernel;
    private AppService $appService;
    private JobScheduler $jobScheduler;

    /**
     * @param AppService $app
     * @param JobScheduler $jobScheduler
     * @param ManagerRegistry $doctrine
     * @param DatabaseLogger $logger
     * @param Kernel $kernel
     * @param string|null $name
     */
    public function __construct(  AppService $app , JobScheduler $jobScheduler , ManagerRegistry $doctrine , #[Autowire(service: 'logger.events')] DatabaseLogger $logger, Kernel $kernel , ?string $name = null)
    {
        parent::__construct($name);
        $this->doctrine = $doctrine;
        $this->logger = $logger;
        $this->kernel = $kernel;
        $this->appService = $app;
        $this->jobScheduler = $jobScheduler;
    }

    /**
     * @return void
     */
    protected function configure(): void
    {
        parent::configure();
        $this->addOption('context' , '-x' , InputOption::VALUE_OPTIONAL , 'Context of this Call' , 'Console' );
        $this->addOption('without-jobs' , null , InputOption::VALUE_NONE , 'Only send due events, no background jobs' );
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
        $lock = FileLock::acquire( $this->getKernel()->getProjectDir() . '/var/daemon-tick.lock' );
        if( $lock === null ){
            $this->getLogger()->log(LogLevel::WARNING , sprintf('%s: Event handler already running, skipped' , $context ) );
            $output->writeln( json_encode( [ 'status' => 'LOCKED' ] , JSON_PRETTY_PRINT ) );
            $this->runJobs( $input );
            return Command::SUCCESS;
        }

        try {
            return $this->tick( $context , $input , $output );
        } finally {
            $lock->release();
            // Nach dem Freigeben und auch, wenn das Senden scheitert: Eine lange Aufgabe darf den nächsten Takt nicht aufhalten, ein
            // Ausfall von Hermine nicht die Aufgaben
            $this->runJobs( $input );
        }
    }

    /**
     * Hintergrundaufgaben – beim Takt per Cron, nicht beim Auslösen aus der Oberfläche (--without-jobs)
     */
    private function runJobs( InputInterface $input ): void
    {
        if( !$input->getOption('without-jobs') ){
            $this->jobScheduler->tick();
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

        $this->getLogger()->log(LogLevel::DEBUG , sprintf('%s: Started event handler' , $context ) );

        $jsonResult = [];
        $now = new DateTime();
        $entityManager = $this->getDoctrine()->getManagerForClass( Event::class );

        // Die Datenbank liefert nur die Kandidaten, ob ein Event wirklich fällig ist, entscheidet Event::isDue() (eine Regel, testbar)
        /* @var $candidates Event[] */
        $candidates = $entityManager->getRepository( Event::class )
            ->createQueryBuilder('entries')
            ->select('entries')
            ->where('entries.dueDateTime <= :now')
            ->andWhere('entries.failedAttempts < :maxFailedAttempts')
            ->setParameter('now' , $now )
            ->setParameter('maxFailedAttempts' , Event::MAX_FAILED_ATTEMPTS )
            ->getQuery()->getResult();
        $events = array_values( array_filter( $candidates , fn( Event $event ) => $event->isDue( $now ) ) );

        $eventsProcessed = 0;
        if( count( $events ) > 0 ){
            // Mediator -> ease of access
            $stashcatMediator = $this->getAppService()->getMediator();
            // Hermine nicht erreichbar oder Anmeldung abgelehnt: kein Fehlversuch der Events, sie warten auf den nächsten Takt
            try {
                if( !$stashcatMediator->login() ){
                    throw new \RuntimeException( 'Hermine rejected the login (next attempt after a pause)' );
                }
                $stashcatMediator->decryptPrivateKey();
                $stashcatMediator->loadCompanies();
                $THWCompany = $stashcatMediator->getCompanyMembers()?->getCompanyByName( $this->getAppService()->getAppConfig()->getStashcatEventCompany() );
                if( $THWCompany === null ){
                    throw new \RuntimeException( 'Company from stashcat_event_company not found' );
                }
            } catch ( \Throwable $e ){
                // In der Pause nach einer Ablehnung nur ein Hinweis, sonst käme jede Minute eine Fehlermeldung (und eine Cron-Mail)
                $paused = $stashcatMediator->isLoginPaused();
                $this->getLogger()->log( $paused ? LogLevel::WARNING : LogLevel::ERROR , sprintf('%s: Hermine not available, %d due events wait' , $context , count( $events ) ) , [ 'error' => $e->getMessage() ] );
                $output->writeln( json_encode( [ 'status' => 'HERMINE_UNAVAILABLE' , 'error' => $e->getMessage() ] , JSON_PRETTY_PRINT ) );
                return $paused ? Command::SUCCESS : Command::FAILURE;
            }

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
     * @return array{text: string, logMessage: string, send: callable}
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
     * @param array<string, mixed> $context
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

}