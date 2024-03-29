<?php

namespace App\Core;

use App\Entity\Event;
use App\Service\AppService;
use App\Stashcat\Entities\Channel;
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

    private array $replaceCallbacks = [];

    private array $serviceInstances = [];

    /**
     * @param ContainerInterface $container
     * @param array $serviceInstances
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
     * @param $key
     * @return string
     */
    protected function getPlaceholderSyntax( $key ): string
    {
        return sprintf('{{%s}}' , $key );
    }

    /**
     * @param $key
     * @return string
     */
    protected function getCommandSyntax( $key ): string
    {
        return sprintf('{%%%s%%}' , $key );
    }

    /**
     * @param $className
     * @return mixed
     */
    protected function getServiceInstance( $className ) : mixed {
        return $this->serviceInstances[ $className ] ?? null;
    }

    /**
     * @param string $eventText
     * @return array|string
     */
    public function replace( string $eventText ): array|string
    {
        foreach( $this->replaceCallbacks AS $realKey => $callback ){
            if( str_contains( $eventText , $realKey ) && ( $callbackResult = $callback() ) !== null && is_scalar( $callbackResult ) ){
                $eventText = str_replace( $realKey , $callbackResult , $eventText );
            }
        }
        return $eventText;
    }

    /**
     * @return array
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
    }

    /**
     * @param string $key
     * @param callable $callback
     * @return void
     */
    protected function addCommandDecoration( string $key , callable $callback ){
        $this->replaceCallbacks[ $this->getCommandSyntax( $key ) ] = $callback;
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

        // Placeholder-file...
        $placeholderFile = Path::normalize( BASE_PATH . trim( $this->container->getParameter('app.message_placeholder') ) );
        if( $filesystem->exists( $placeholderFile ) ){
            $placeholderData = json_decode( file_get_contents( $placeholderFile ) , true );
            if( is_array( $placeholderData ) ){
                foreach( $placeholderData AS $key => $data ){
                    $this->addPlaceholderDecoration( $key , fn() => ( isset( $data['message'] ) && is_string( $data['message'] ) ) ? $data['message'] : null );
                }
            }
        }

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
                                        $rest = new RestClient( RestClient::RESPONSE_FORMAT_JSON );
                                        $result = $rest->post( $data['url'] , json_encode( $data['payload'] ) , null , [ 'Content-Type' => 'application/json'] );
                                        return $result['content'] ?? $result['message'] ?? null;
                                    }
                                    return null;
                                } );
                                break;

                            case 'ICAL_DOWNLOAD_VTODO':
                                $this->addCommandDecoration( $key , function() use ($data){
                                    if( isset( $data['url'] ) ){

                                        $mode = $data['events'] ?? 'OPEN';
                                        $rest = new RestClient( RestClient::RESPONSE_FORMAT_PLAIN );
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
                                                    if( $priority > 5 ){
                                                        $priorityText = '🟦 niedrig (' . $priority . ')';
                                                    } else if( $priority < 5 ){
                                                        $priorityText = '🟥 hoch (' . $priority . ')';
                                                    } else {
                                                        $priorityText = '🟨 mittel (' . $priority . ')';
                                                    }
                                                    $sortKey = $priority . '_' . $sortKey;
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
                                } );
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
            $THWGroups = $stashcatMediator->getGroupsOfCompany( $stashCatCompany );
            $groupStatistics = [];
            foreach( $THWGroups AS $group ){
                $groupStatistics[ $group->getPossibleUser() ] = sprintf('%s: %s mögliche Nutzer' , $group->getName() , $group->getPossibleUser() );
            }
            ksort( $groupStatistics );
            return implode( "\r\n" , $groupStatistics );
        } );

        $this->addPlaceholderDecoration( 'EventInterval' , fn() => !empty( $event?->getDateInterval() ) && is_array( $allowedIntervals ) ? ( $allowedIntervals[ $event->getDateInterval() ] ?? '#N/A' ) : null );
    }
}