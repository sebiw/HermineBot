<?php

namespace App\Core;

use App\Entity\LogEntry;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\AbstractLogger;

class DatabaseLogger extends AbstractLogger {

    private string $channel;
    private ManagerRegistry $managerRegistry;

    private array $ignoreLevel = [];

    /**
     * @param string $channel
     * @param ManagerRegistry $managerRegistry
     */
    public function __construct( string $channel , ManagerRegistry $managerRegistry , array $ignoreLevel = [] )
    {
        $this->channel = $channel;
        $this->managerRegistry = $managerRegistry;
        $this->ignoreLevel = $ignoreLevel;
    }

    /**
     * @param $level
     * @param \Stringable|string $message
     * @param array $context
     */
    public function log($level, \Stringable|string $message, array $context = []): void
    {
        if( in_array( $level , $this->ignoreLevel ) ){
            return;
        }

        $logEntry = new LogEntry( $this->channel , $level , $message , $context );
        $objectManager = $this->managerRegistry->getManagerForClass( LogEntry::class );
        $objectManager->persist( $logEntry );
        $objectManager->flush();
    }
}