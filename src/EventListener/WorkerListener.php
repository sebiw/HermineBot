<?php

// src/EventListener/ExceptionListener.php
namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;

#[AsEventListener]
class WorkerListener
{
    /**
     * Stop Worker if he idles - make messenger:consume cron-able (for Web-Spaces)!
     * @param WorkerRunningEvent $event
     * @return void
     */
    public function __invoke(WorkerRunningEvent $event): void
    {
        if( $event->isWorkerIdle() ){
            $event->getWorker()->stop();
        }
    }
}