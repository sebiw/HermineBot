<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    /** Zeitzone der Events (Eingabe in der Oberfläche, Vergleich im Cron), unabhängig von der php.ini; optional APP_TIMEZONE */
    const DEFAULT_TIMEZONE = 'Europe/Berlin';

    public function boot(): void
    {
        date_default_timezone_set( $_SERVER['APP_TIMEZONE'] ?? $_ENV['APP_TIMEZONE'] ?? self::DEFAULT_TIMEZONE );
        parent::boot();
    }
}
