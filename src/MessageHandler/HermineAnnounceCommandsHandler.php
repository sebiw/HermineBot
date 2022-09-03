<?php

namespace App\MessageHandler;

use App\Core\DatabaseLogger;
use App\Kernel;
use HermineBotCom\AnnounceCommands;
use HermineBotCom\Placeholder;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class HermineAnnounceCommandsHandler
{

    protected Kernel $kernel;

    protected DatabaseLogger $logger;

    public function __construct( Kernel $kernel , #[Autowire(service: 'logger.events')] DatabaseLogger $logger )
    {
        $this->kernel = $kernel;
        $this->logger = $logger;
    }

    public function __invoke( AnnounceCommands $announcedCommands )
    {
        $filesystem = new Filesystem();
        $commandDataFile = Path::normalize( BASE_PATH . trim( $this->kernel->getContainer()->getParameter('app.message_command_content') ) );

        $commandData = [];
        if( $filesystem->exists( $commandDataFile ) ){
            $commandData = json_decode( file_get_contents( $commandDataFile ) , true );
        }

        foreach( $announcedCommands->getCommands() AS $command ){
            $commandName = sprintf('%s:%s' , $announcedCommands->getContext() , $command );
            $commandDateTime = $announcedCommands->getCreatedDateTime()->format(\DateTime::W3C);

            if( !isset( $commandData[ $commandName ] ) ){
                $commandData[ $commandName ] = [];
            }

            $commandData[ $commandName ]['command'] = $command;
            $commandData[ $commandName ]['command_datetime'] = $commandDateTime;
            $commandData[ $commandName ]['context'] = $announcedCommands->getContext();
        }

        $fileContent = json_encode( $commandData, JSON_PRETTY_PRINT );
        $filesystem->dumpFile( $commandDataFile , $fileContent );

        $this->logger->info(sprintf('New CommandData-File written! %s DataKeys exist!' , count( $commandData ) ) , [ 'filepath' => $commandDataFile ]);
    }

}
