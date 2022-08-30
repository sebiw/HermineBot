<?php

// src/Command/CreateUserCommand.php
namespace App\Command;

use App\Core\DatabaseLogger;
use App\Kernel;
use App\Service\AppService;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

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

        // Prepare Message...
        $placeholderCallbacks = self::getMessageCallbackDecorator( $this->getKernel()->getContainer() , [
            'CompanyActiveUser' => $THWCompany->getActiveUser(),
            'CompanyCreatedUser' => $THWCompany->getCreatedUser(),
            'CompanyName' => $THWCompany->getName(),
            'ChannelUserCount' => $THWChannel->getUserCount(),
            'CompanyGroupsStatsCallback' => function() use ( $THWCompany , $stashcatMediator ){
                $stashcatMediator->loadGroups( $THWCompany );
                $THWGroups = $stashcatMediator->getGroupsOfCompany( $THWCompany );
                $groupStatistics = [];
                foreach( $THWGroups AS $group ){
                    $groupStatistics[ $group->getPossibleUser() ] = sprintf('%s: %s mögliche Nutzer' , $group->getName() , $group->getPossibleUser() );
                }
                ksort( $groupStatistics );
                return implode( "\r\n" , $groupStatistics );
            }
        ]);

        $eventText = self::replaceByCallbacks( $placeholderCallbacks , $eventText );

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

    /**
     * @param array $placeholderCallbacks
     * @param string $eventText
     * @return array|string
     */
    public static function replaceByCallbacks( array $placeholderCallbacks , string $eventText ): array|string
    {
        foreach( $placeholderCallbacks AS $key => $callback ){
            $realKey = '{{' . $key . '}}';
            if( str_contains( $eventText , $realKey ) && ( $callbackResult = $callback() ) !== null && is_string( $callbackResult ) ){
                $eventText = str_replace( $realKey , $callbackResult , $eventText );
            }
        }
        return $eventText;
    }

    /**
     * @param Container $container
     * @param array $contextData
     * @return array
     */
    public static function getMessageCallbackDecorator( ContainerInterface $container , array $contextData = [] ): array
    {
        $returnCallbacks = [];
        $filesystem = new Filesystem();
        $placeholderFile = Path::normalize( BASE_PATH . trim( $container->getParameter('app.message_placeholder') ) );
        if( $filesystem->exists( $placeholderFile ) ){
            $placeholderData = json_decode( file_get_contents( $placeholderFile ) , true );
            if( is_array( $placeholderData ) ){
                foreach( $placeholderData AS $key => $data ){
                    // Return NULL => No replacement possible -> ERROR, Return String => Nice!
                    $returnCallbacks[ $key ] = function() use ( $data ){
                        if( isset( $data['message'] ) && is_string( $data['message'] ) ){
                            return $data['message'];
                        }
                        return null;
                    };
                }
            }
        }

        // Pass through...
        foreach( [ 'CompanyActiveUser' , 'CompanyCreatedUser' , 'CompanyName' , 'ChannelUserCount' , 'EventInterval' ] AS $key ){
            $returnCallbacks[ $key ] = function() use ( $contextData , $key ){
                if( isset( $contextData[ $key ] ) && is_scalar( $contextData[ $key ] ) ){
                    return (string) $contextData[ $key ];
                }
                return null;
            };
        }

        // Callback pass through
        foreach( [ 'CompanyGroupsStats' => 'CompanyGroupsStatsCallback' ] AS $key => $contextKey ){
            $returnCallbacks[ $key ] = function() use ( $contextData , $contextKey ){
                if( isset( $contextData[ $contextKey ] ) && is_callable( $contextData[ $contextKey ] ) ){
                    return $contextData[ $contextKey ]();
                }
                return null;
            };
        }

        return $returnCallbacks;
    }

}