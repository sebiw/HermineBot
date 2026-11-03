<?php

namespace App\Core;

use App\Entity\Event;
use App\Service\AppService;
use App\Stashcat\Entities\Channel;
use App\Stashcat\Entities\Group;
use App\Stashcat\Entities\Company;
use DateTimeInterface;
use Sabre\VObject\Component\VTodo;
use Sabre\VObject\Property;
use Sabre\VObject\Reader;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

class MessageDecorator {

    private ContainerInterface $container;

    /** @var array<string, callable> Platzhalter-Syntax => liefert den Ersatz */
    private array $replaceCallbacks = [];

    /** @var array<class-string, object> */
    private array $serviceInstances = [];

    /** @var array<string, array{label: string, group: string}> Platzhalter-Syntax => Name und Gruppe für die Auswahl im Formular */
    private array $descriptions = [];

    const GROUP_HERMINE = 'Aus Hermine';
    const GROUP_EVENT = 'Zu diesem Event';
    const GROUP_SOURCES = 'Aus anderen Quellen (beim Senden abgerufen)';

    /** Namen der fest eingebauten Platzhalter */
    const PLACEHOLDER_LABELS = [
        'CompanyName' => [ 'Name der Firma' , self::GROUP_HERMINE ],
        'CompanyActiveUser' => [ 'aktive Nutzer' , self::GROUP_HERMINE ],
        'CompanyCreatedUser' => [ 'angelegte Nutzer' , self::GROUP_HERMINE ],
        'CompanyGroupsStats' => [ 'Gruppen mit Nutzerzahl' , self::GROUP_HERMINE ],
        'ChannelUserCount' => [ 'Mitglieder des Channels' , self::GROUP_HERMINE ],
        'EventInterval' => [ 'Wiederholung' , self::GROUP_EVENT ],
    ];

    /**
     * @param ContainerInterface $container
     * @param list<mixed> $serviceInstances Objekte für die Platzhalter (Firma, Channel, AppService, Event)
     * @throws \Exception
     */
    public function __construct( ContainerInterface $container, array $serviceInstances = [] )
    {
        foreach( $serviceInstances AS $serviceInstance ){
            if( is_object( $serviceInstance ) && !isset( $this->serviceInstances[ get_class( $serviceInstance ) ] ) ){
                $this->serviceInstances[ get_class( $serviceInstance ) ] = $serviceInstance;
            } else {
                throw new \Exception('Service-Instance already exist or is not a Object!');
            }
        }
        $this->container = $container;
        $this->setup();
    }

    /**
     * @param string $key
     * @return string
     */
    protected function getPlaceholderSyntax( string $key ): string
    {
        return sprintf('{{%s}}' , $key );
    }

    /**
     * @param string $key
     * @return string
     */
    protected function getCommandSyntax( string $key ): string
    {
        return sprintf('{%%%s%%}' , $key );
    }

    /**
     * @param class-string $className
     * @return mixed
     */
    protected function getServiceInstance( string $className ) : mixed {
        return $this->serviceInstances[ $className ] ?? null;
    }

    /**
     * @param string $eventText
     * @return string
     */
    public function replace( string $eventText ): string
    {
        // Erst alle Ersetzungen für den Originaltext ermitteln, dann in einem Durchgang ersetzen:
        // Platzhalter, die erst durch ein eingesetztes Ergebnis in den Text kommen (z. B. aus externen Quellen), werden nicht ausgewertet
        $replacements = [];
        foreach( $this->replaceCallbacks AS $realKey => $callback ){
            if( str_contains( $eventText , $realKey ) && ( $callbackResult = $callback() ) !== null && is_scalar( $callbackResult ) ){
                $replacements[ $realKey ] = (string) $callbackResult;
            }
        }
        return strtr( $eventText , $replacements );
    }

    /**
     * Platzhalter für die Auswahl im Formular und die Anzeige in der Übersicht, in der Reihenfolge der Gruppen
     * @return list<array{code: string, label: string, group: string}>
     */
    public function describeReplacements(): array
    {
        $order = [ self::GROUP_HERMINE => 0 , self::GROUP_EVENT => 1 , self::GROUP_SOURCES => 2 ];
        $rows = [];
        foreach( $this->descriptions AS $code => $description ){
            $rows[] = [ 'code' => $code ] + $description;
        }
        usort( $rows , fn( array $a , array $b ) => $order[ $a['group'] ] <=> $order[ $b['group'] ] );
        return $rows;
    }

    /**
     * Anzeigename einer externen Quelle: Feld „label“ in command_api_endpoints.json, sonst der Name des Befehls
     * @param array<mixed> $data
     */
    private static function configLabel( array $data ): ?string
    {
        return isset( $data['label'] ) && is_string( $data['label'] ) && trim( $data['label'] ) !== '' ? trim( $data['label'] ) : null;
    }

    /**
     * @return list<string>
     */
    public function getAvailableReplacements(): array
    {
        return array_keys( $this->replaceCallbacks );
    }

    /**
     * @param string $key
     * @param callable $callback
     * @return void
     */
    protected function addPlaceholderDecoration( string $key , callable $callback ){
        $this->replaceCallbacks[ $this->getPlaceholderSyntax( $key ) ] = $callback;
        [ $label , $group ] = self::PLACEHOLDER_LABELS[ $key ] ?? [ $key , self::GROUP_HERMINE ];
        $this->descriptions[ $this->getPlaceholderSyntax( $key ) ] = [ 'label' => $label , 'group' => $group ];
    }

    /**
     * @param string $key
     * @param callable $callback
     * @return void
     */
    protected function addCommandDecoration( string $key , callable $callback , ?string $label = null ){
        $this->descriptions[ $this->getCommandSyntax( $key ) ] = [ 'label' => $label ?? $key , 'group' => self::GROUP_SOURCES ];
        // Externe Quelle ohne Ergebnis: Fehler statt Nachricht mit rohem {%…%} – der Daemon zählt dann einen Fehlversuch und versucht es erneut
        $this->replaceCallbacks[ $this->getCommandSyntax( $key ) ] = function() use ( $key , $callback ){
            $result = $callback();
            if( $result === null || !is_scalar( $result ) ){
                throw new \RuntimeException( sprintf( 'Command %s returned no content' , $key ) );
            }
            return $result;
        };
    }

    /**
     * @return void
     * @throws \Exception
     */
    private function setup(): void
    {
        $filesystem = new Filesystem();
        /* @var $appService AppService */
        $appService = $this->getServiceInstance( AppService::class );
        /* @var $stashCatCompany Company|null */
        $stashCatCompany = $this->getServiceInstance( Company::class );
        /* @var $stashCatChannel Channel|null */
        $stashCatChannel = $this->getServiceInstance( Channel::class );
        /* @var $stashcatMediator StashcatMediator */
        $stashcatMediator = $appService?->getMediator();
        /* @var $event Event */
        $event = $this->getServiceInstance( Event::class );

        /* @var $allowedIntervals array|null */
        $allowedIntervals = $appService?->getAppConfig()?->getAllowedIntervals();

        // Commands...
        $commandContentFile = Path::normalize( BASE_PATH . trim( $this->container->getParameter('app.message_command_api_endpoints') ) );
        if( $filesystem->exists( $commandContentFile ) ){
            $commandContentData = json_decode( file_get_contents( $commandContentFile ) , true );
            if( is_array( $commandContentData ) ){
                foreach( $commandContentData AS $key => $data ){
                    // Data => URL DATA

                    if( isset( $data['type'] ) && is_string( $data['type'] ) ){
                        switch( strtoupper( $data['type'] ) ){
                            case 'REST_JSON' :
                                $this->addCommandDecoration( $key , function() use ($data){
                                    if( isset( $data['url'] ) && isset( $data['payload'] ) ){
                                        $rest = new RestClient( RestClient::RESPONSE_FORMAT_JSON , null , true );
                                        $result = $rest->post( $data['url'] , json_encode( $data['payload'] , JSON_THROW_ON_ERROR ) , null , [ 'Content-Type' => 'application/json'] );
                                        // Nur der Inhalt: eine Fehlermeldung der Gegenseite (message) gehört nicht in den Channel
                                        return is_array( $result ) ? ( $result['content'] ?? null ) : null;
                                    }
                                    return null;
                                } , self::configLabel( $data ) );
                                break;

                            case 'ICAL_DOWNLOAD_VTODO':
                                $this->addCommandDecoration( $key , function() use ($data){
                                    if( isset( $data['url'] ) ){

                                        $mode = $data['events'] ?? 'OPEN';
                                        $rest = new RestClient( RestClient::RESPONSE_FORMAT_PLAIN , null , true );
                                        $additionalHeader = [];
                                        if( isset( $data['authorization_basic'] , $data['authorization_basic']['user'] , $data['authorization_basic']['password'] ) ){
                                            $basicKey = $data['authorization_basic']['user'] . ':' . $data['authorization_basic']['password'];
                                            $basicKey = base64_encode( $basicKey );
                                            $additionalHeader['Authorization'] = 'BASIC ' . $basicKey;
                                        }
                                        $result = $rest->get( $data['url'] , null , null , $additionalHeader );
                                        $vCalendar = Reader::read( $result , Reader::OPTION_FORGIVING );

                                        $vToDos = [];

                                        $completedLimit = ( new \DateTime() )->modify('-4 weeks');
                                        $completedLimitRecently = ( new \DateTime() )->modify('-1 weeks')->modify('-5 days');
                                        $counter = 0;

                                        if( isset( $vCalendar->VTODO ) ){
                                            /* @var $todo VTodo */
                                            foreach($vCalendar->VTODO as $todo) {
                                                // Title
                                                $title = (string) $todo->SUMMARY;
                                                $counter++;

                                                $sortKey = $title . '_' . $counter;

                                                $isCompleted = false;
                                                $completedDateTime = null;

                                                // Status
                                                $statusText = null;
                                                if( isset( $todo->STATUS ) ){
                                                    $status = strtoupper( (string) $todo->STATUS );
                                                    $statusText = match( $status ){
                                                        'CANCELLED' => '❌ Abgebrochen',
                                                        'COMPLETED' => '✅ Fertiggestellt',
                                                        'IN-PROCESS' => '🛠️ In Bearbeitung',
                                                        'NEEDS-ACTION' => '⚠️ Handlungsbedarf',
                                                        default => $status
                                                    };
                                                    $isCompleted = ($status == 'COMPLETED');
                                                }

                                                if( $isCompleted && isset( $todo->COMPLETED ) ){
                                                    $completedDateTime = new \DateTime( $todo->COMPLETED->getValue() );
                                                }

                                                if( $mode === 'OPEN' && $isCompleted ){
                                                    continue;
                                                } else if( $mode === 'COMPLETED' ){
                                                    if( $isCompleted && $completedDateTime !== null && $completedDateTime < $completedLimit ){
                                                        continue;
                                                    } else if( !$isCompleted) {
                                                        continue;
                                                    }
                                                } else if( $mode === 'COMPLETED_RECENTLY' ){
                                                    if( $isCompleted && $completedDateTime !== null && $completedDateTime < $completedLimitRecently ){
                                                        continue;
                                                    } else if( !$isCompleted) {
                                                        continue;
                                                    }
                                                }

                                                // Prio
                                                $priorityText = null;
                                                if( isset( $todo->PRIORITY ) && $todo->PRIORITY instanceof Property ){
                                                    $priority = (int) $todo->PRIORITY->getValue();
                                                    $priorityText = self::priorityText( $priority );
                                                    if( $priorityText !== null ){
                                                        $sortKey = $priority . '_' . $sortKey;
                                                    }
                                                }

                                                // Description
                                                $additionalDescription = null;
                                                if( isset( $todo->DESCRIPTION ) ){
                                                    $additionalDescription = (string) $todo->DESCRIPTION;
                                                }

                                                $priorityText = ( $priorityText ? 'Priorität: ' . $priorityText : null );
                                                $statusText = ( $statusText ? 'Status: ' . $statusText : null );
                                                $hints = array_filter( [ $priorityText , $statusText ] );

                                                $toDoText = '🔖 ' . $title . ( !empty( $hints ) ? PHP_EOL . '     _' . implode(', ' , $hints ) . '_' : '' ) . ( $additionalDescription ? PHP_EOL . '     _' . $additionalDescription  . '_' : '' );
                                                $vToDos[ $sortKey ] = $toDoText;
                                            }
                                        }

                                        ksort( $vToDos , SORT_REGULAR );

                                        if( empty( $vToDos ) ){
                                            if( $mode === 'COMPLETED' ){
                                                $vToDos[] = 'In letzter Zeit wurden keine Aufgaben erledigt!';
                                            } else {
                                                $vToDos[] = 'Derzeit gibt es keine Aufgaben!';
                                            }
                                        }

                                        return implode( PHP_EOL , $vToDos );
                                    }
                                    return null;
                                } , self::configLabel( $data ) );
                                break;
                        }
                    }


                }
            }
        }

        $this->addPlaceholderDecoration('CompanyActiveUser' , fn() => $stashCatCompany?->getActiveUser() );
        $this->addPlaceholderDecoration('CompanyCreatedUser' , fn() => $stashCatCompany?->getCreatedUser() );
        $this->addPlaceholderDecoration('CompanyName' , fn() => $stashCatCompany?->getName() );

        $this->addPlaceholderDecoration('ChannelUserCount' , fn() => $stashCatChannel?->getUserCount() );

        $this->addPlaceholderDecoration('CompanyGroupsStats' , function() use ( $stashCatCompany , $stashcatMediator ){
            if( $stashCatCompany === null || $stashcatMediator === null ){
                return null;
            }
            $stashcatMediator->loadGroups( $stashCatCompany );
            return self::groupStatsText( $stashcatMediator->getGroupsOfCompany( $stashCatCompany ) );
        } );

        $this->addPlaceholderDecoration( 'EventInterval' , fn() => !empty( $event?->getDateInterval() ) && is_array( $allowedIntervals ) ? ( $allowedIntervals[ $event->getDateInterval() ] ?? '#N/A' ) : null );
    }

    /**
     * {{CompanyGroupsStats}}: je Gruppe eine Zeile, aufsteigend nach Zahl der möglichen Nutzer, bei gleicher Zahl nach Name
     * (vorher war die Zahl der Schlüssel, Gruppen mit gleicher Zahl verdrängten sich)
     * @param iterable<Group> $groups
     */
    public static function groupStatsText( iterable $groups ): string
    {
        $rows = [];
        foreach( $groups AS $group ){
            $rows[] = [ (int) $group->getPossibleUser() , (string) $group->getName() ];
        }
        usort( $rows , fn( array $a , array $b ) => [ $a[0] , $a[1] ] <=> [ $b[0] , $b[1] ] );
        return implode( "\r\n" , array_map( fn( array $row ) => sprintf( '%s: %d mögliche Nutzer' , $row[1] , $row[0] ) , $rows ) );
    }

    /**
     * Priorität einer Aufgabe nach RFC 5545: 1 (hoch) bis 9 (niedrig), 5 mittel; 0 = nicht festgelegt (keine Angabe, sortiert wie ohne)
     */
    public static function priorityText( int $priority ): ?string
    {
        return match( true ){
            $priority < 1 || $priority > 9 => null,
            $priority < 5 => '🟥 hoch (' . $priority . ')',
            $priority > 5 => '🟦 niedrig (' . $priority . ')',
            default => '🟨 mittel (' . $priority . ')',
        };
    }
}
