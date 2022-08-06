<?php

namespace App\Core;

use App\Entity\LogEntry;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\AbstractLogger;

class DatabaseLogger extends AbstractLogger {

    private string $channel;
    private ManagerRegistry $managerRegistry;

    /**
     * @param string $channel
     * @param ManagerRegistry $managerRegistry
     */
    public function __construct( string $channel , ManagerRegistry $managerRegistry )
    {
        $this->channel = $channel;
        $this->managerRegistry = $managerRegistry;
    }

    /**
     * @param $level
     * @param \Stringable|string $message
     * @param array $context
     */
    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $logEntry = new LogEntry( $this->channel , $level , $message , $context );
        $objectManager = $this->managerRegistry->getManagerForClass( LogEntry::class );
        $objectManager->persist( $logEntry );
        $objectManager->flush();
    }
}