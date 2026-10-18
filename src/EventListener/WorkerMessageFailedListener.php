<?php

namespace App\EventListener;

use App\Core\DatabaseLogger;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

#[AsEventListener]
class WorkerMessageFailedListener
{

    protected DatabaseLogger $logger;

    public function __construct( #[Autowire(service: 'logger.events')] DatabaseLogger $logger )
    {
        $this->logger = $logger;
    }

    /**
     * Endgültig fehlgeschlagene Queue-Nachrichten im Log sichtbar machen (liegen danach im Failure-Transport)
     * @param WorkerMessageFailedEvent $event
     * @return void
     */
    public function __invoke( WorkerMessageFailedEvent $event ): void
    {
        if( $event->willRetry() ){
            $this->logger->warning('Queued message failed, will be retried' , [ 'error' => $event->getThrowable()->getMessage() ]);
        } else {
            $this->logger->critical('Queued message failed permanently, moved to failure transport (messenger:failed:show)' , [ 'error' => $event->getThrowable()->getMessage() ]);
        }
    }
}
