<?php

namespace App\MessageHandler;

use App\Core\DatabaseLogger;
use App\Kernel;
use HermineBotCom\Placeholder;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class HerminePlaceholderHandler
{

    protected Kernel $kernel;

    protected DatabaseLogger $logger;

    public function __construct( Kernel $kernel , #[Autowire(service: 'logger.events')] DatabaseLogger $logger )
    {
        $this->kernel = $kernel;
        $this->logger = $logger;
    }

    public function __invoke( Placeholder $message )
    {
        $filesystem = new Filesystem();
        $placeholderFile = Path::normalize( BASE_PATH . trim( $this->kernel->getContainer()->getParameter('app.message_placeholder') ) );

        $placeholderData = [];
        if( $filesystem->exists( $placeholderFile ) ){
            $placeholderData = json_decode( file_get_contents( $placeholderFile ) , true );
        }

        $placeholderName = sprintf('%s:%s' , $message->getContext() , $message->getKey() );
        $placeholderMessage = $message->getMessage();
        $placeholderDate = $message->getCreatedDateTime()->format(\DateTime::W3C);

        $placeholderData[ $placeholderName ] = [
            'message' => $placeholderMessage,
            'message_datetime' => $placeholderDate
        ];

        $fileContent = json_encode( $placeholderData, JSON_PRETTY_PRINT );
        $filesystem->dumpFile( $placeholderFile , $fileContent );

        $this->logger->info(sprintf('New Placeholder-File written! %s Placeholders exist!' , count( $placeholderData ) ) , [ 'filepath' => $placeholderFile ]);
    }

}
