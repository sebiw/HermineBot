<?php

namespace App\MessageHandler;

use App\Core\DatabaseLogger;
use App\Kernel;
use HermineBotCom\Message;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class HermineMessageHandler
{

    protected Kernel $kernel;

    protected DatabaseLogger $logger;

    public function __construct( Kernel $kernel , #[Autowire(service: 'logger.events')] DatabaseLogger $logger )
    {
        $this->kernel = $kernel;
        $this->logger = $logger;
    }

    public function __invoke( Message $message )
    {

        $application = new Application( $this->kernel );
        $application->setAutoExit(false);

        $input = new ArrayInput([
            'command' => 'hermine:send-message',
            '--context' => $message->getContext(),
            '--channel' => $message->getChannel(),
            '--message' => $message->getMessage()
        ]);

        // You can use NullOutput() if you don't need the output
        $output = new BufferedOutput();
        if( $application->run($input, $output) === Command::SUCCESS ){
            $this->logger->info('Message Event processed' );
        } else {
            $this->logger->emergency('Message Event failed' , ['error' => $output->fetch()]);
        }
    }

}
