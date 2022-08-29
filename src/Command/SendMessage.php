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
#[AsCommand(name: 'hermine:send-message', description: 'Send on Message to a Hermine-Channel')]
class SendMessage extends Command
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

    protected function configure()
    {
        parent::configure();
        $this->addOption('context' , '-x' , InputOption::VALUE_OPTIONAL , 'Context of this Call' , 'Console' );
        $this->addOption('channel' , '-c' , InputOption::VALUE_REQUIRED , 'Target-Channel' , 'T_OWBH_TEST' );
        $this->addOption('message' , '-m' , InputOption::VALUE_REQUIRED , 'Message to send' );
        $this->addOption('message-b64' , '-b' , InputOption::VALUE_REQUIRED , 'Message to send as Base64 encoded string' );
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

        $channelTarget = trim( $input->getOption('channel') ?? '' );
        if( empty( $channelTarget ) || !in_array( $channelTarget , $this->getAppService()->getAppConfig()->getAllowedChannelNames() ) ){
            throw new \Exception('Invalid Channel-Target: ' . $channelTarget );
        }

        foreach( [ 'message' => null , 'message-b64' => 'base64_decode' ] AS $key => $callback ){
            $eventText =  trim( $input->getOption( $key ) ?? '' );
            if( !empty( $eventText ) && strlen( $eventText ) > 0 ){
                if( $callback !== null && is_callable( $callback ) ){
                    $eventText = $callback( $eventText );
                }
                break;
            }
        }

        if( empty( $eventText ) ){
            throw new \Exception('Sending empty Messages is not allowed!');
        }

        $this->getLogger()->log(LogLevel::INFO , sprintf('%s: Started event handler' , $context ) );

        $jsonResult = [];

        $stashcatMediator = $this->getAppService()->getMediator();
        $stashcatMediator->login();
        $stashcatMediator->decryptPrivateKey();
        $stashcatMediator->loadCompanies();

        $THWCompany = $stashcatMediator->getCompanyMembers()->getCompanyByName('THW');
        $stashcatMediator->loadChannelSubscripted( $THWCompany );

        $THWChannel = $stashcatMediator->getChannelOfCompany( $channelTarget , $THWCompany );
        $stashcatMediator->decryptChannelPrivateKey( $THWChannel );

        // Send message!
        $result = $stashcatMediator->sendMessageToChannel( $eventText . $this->getAppService()->getAppConfig()->getAutoAppendToMessages() , $THWChannel );

        if( $result->_getStatusValue() == 'OK' ){
            $this->getLogger()->log(LogLevel::INFO , sprintf('Message sent to channel %s' , $channelTarget ) , [ 'text' => $eventText ]);
        }

        $stashcatMediator->likeMessage( $result->getMessage() );

        $jsonResult['status'] = 'OK';
        $jsonResult['events'] = [
            'processed' => 1,
            'details' => [
                'channelTarget' => $channelTarget,
                'eventText' => $eventText
            ]
        ];

        $output->writeln( json_encode( $jsonResult , JSON_PRETTY_PRINT ) );
        return Command::SUCCESS;
    }

}