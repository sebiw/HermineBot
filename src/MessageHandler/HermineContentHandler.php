<?php

namespace App\MessageHandler;

use App\Core\DatabaseLogger;
use App\Kernel;
use HermineBotCom\AnnounceCommands;
use HermineBotCom\Content;
use HermineBotCom\Placeholder;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class HermineContentHandler
{

    protected Kernel $kernel;

    protected DatabaseLogger $logger;

    public function __construct( Kernel $kernel , #[Autowire(service: 'logger.events')] DatabaseLogger $logger )
    {
        $this->kernel = $kernel;
        $this->logger = $logger;
    }

    public function __invoke( Content $content )
    {
        $filesystem = new Filesystem();
        $commandDataFile = Path::normalize( BASE_PATH . trim( $this->kernel->getContainer()->getParameter('app.message_command_content') ) );

        $commandData = [];
        if( $filesystem->exists( $commandDataFile ) ){
            $commandData = json_decode( file_get_contents( $commandDataFile ) , true );
        }


        $commandName = sprintf('%s:%s' , $content->getContext() , $content->getKey() );
        $commandDateTime = $content->getCreatedDateTime()->format(\DateTime::W3C);
        $commandData[ $commandName ] = [
            'message' => $content->getMessage(),
            'message_datetime' => $commandDateTime,
            'context' => $content->getContext()
        ];


        $fileContent = json_encode( $commandData, JSON_PRETTY_PRINT );
        $filesystem->dumpFile( $commandDataFile , $fileContent );

        $this->logger->info(sprintf('New Placeholder-File written! %s Placeholders exist!' , count( $commandData ) ) , [ 'filepath' => $commandDataFile ]);
    }

}
