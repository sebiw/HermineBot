<?php

namespace App\Twig;

use App\Service\AppService;
use Twig\Environment;
use Twig\Extension\CoreExtension;
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
            // Zusammengehörendes bricht nicht um (Datum mit Uhrzeit, Namen), wie im StAN-Manager; wirkt auch in der Kartenansicht
            new TwigFilter('no_break', [ self::class , 'noBreak' ]),
            // Text eines Events in der Übersicht, Argument: Platzhalter => Name
            new TwigFilter('event_text', [ \App\Core\Events\EventTextHtml::class , 'render' ] , [ 'is_safe' => [ 'html' ] ]),
        );
    }

    /**
     * Wie der Filter date von Twig, Standardformat aus env.app.php (Datum und Uhrzeit wie im StAN-Manager ohne Bindestrich)
     * @param \DateTimeInterface|string|int|null $date
     */
    public function dateFilter( mixed $date , ?string $format = null , \DateTimeZone|string|false|null $timezone = null ): string
    {
        if($date !== null) {
            if( $format === null ){
                $format = $this->appService->getAppConfig()->getDateFormat() . ' ' . $this->appService->getAppConfig()->getTimeFormat();
            }
            return $this->twig->getExtension( CoreExtension::class )->formatDate( $date , $format , $timezone );
        }
        return "";
    }

    /**
     * Leerzeichen und Bindestriche geschützt, damit nichts darin umbricht (gleich wie im StAN-Manager)
     */
    public static function noBreak( ?string $text ): string
    {
        return str_replace( [ ' ' , '-' ] , [ "\u{00A0}" , "\u{2011}" ] , (string) $text );
    }
}
