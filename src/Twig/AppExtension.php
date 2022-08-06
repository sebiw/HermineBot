<?php

namespace App\Twig;

use App\Service\AppService;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class AppExtension extends AbstractExtension {

    private AppService $appService;

    private Environment $twig;

    public function __construct( AppService $app , Environment $twig )
    {
        $this->appService = $app;
        $this->twig = $twig;
    }

    public function getFilters(): array
    {
        return array(
            new TwigFilter('date', array($this, 'dateFilter')),
        );
    }

    public function dateFilter($date, $format = null , $timezone = null ): string
    {
        if($date !== null) {
            if( $format === null ){
                $format = $this->appService->getAppConfig()->getDateFormat() . ' - ' . $this->appService->getAppConfig()->getTimeFormat();
            }
            return twig_date_format_filter( $this->twig , $date , $format , $timezone );
        }
        return "";
    }
}