<?php

namespace App\Core;

use App\Entity\Event;
use App\Service\AppService;
use App\Stashcat\Entities\Channel;
use App\Stashcat\Entities\Company;
use DateTimeInterface;
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
                $this->serviceInstances[ get_class( $serviceInstance ) ] = $serviceInstances;
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
    public function getPlaceholderSyntax( $key ): string
    {
        return sprintf('{{%s}}' , $key );
    }

    /**
     * @param $key
     * @return string
     */
    public function getCommandSyntax( $key ): string
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
     * @return array
     */
    public function checkRequirements( string $eventText ): array
    {
        $defaultLifeTime = new \DateInterval('PT1M'); // Data min lifetime 1 min
        $overdueCommands = [];

        $filesystem = new Filesystem();
        $commandContentFile = Path::normalize( BASE_PATH . trim( $this->container->getParameter('app.message_command_content') ) );
        if( $filesystem->exists( $commandContentFile ) ){
            $commandContent = json_decode( file_get_contents( $commandContentFile ) , true );
            if( is_array( $commandContent ) ){
                foreach( $commandContent AS $key => $data ){
                    $command = $this->getCommandSyntax( $key );
                    if( str_contains( $eventText , $command ) && isset( $data['message_datetime'] ) && isset( $data['command_datetime'] ) ){
                        $messageDateTime = \DateTime::createFromFormat( DateTimeInterface::W3C , $data['message_datetime'] ); // last time the Data was updated
                        $commandDateTime = \DateTime::createFromFormat( DateTimeInterface::W3C , $data['command_datetime'] ); // maybe once a day the commands will be populated ???

                        // Prüfen vor dem Event auf Requirements?
                        // Einholen von den Voraussetzungen durch Events
                        // => Hoffen das die Ergebnisse eintreffen bevor die Nachricht verschickt wird?
                        // ORDER: DELAY der Message BIS die Ergebnisse da sind!!!! => sinnvoller
                        if( $messageDateTime->sub( $defaultLifeTime ) < (new \DateTime()) ){
                            $overdueCommands[] = $key;
                        }
                    }
                }
            }
        }
        return $overdueCommands;
    }

    /**
     * @param string $eventText
     * @return array|string
     */
    public function replace( string $eventText ): array|string
    {
        foreach( $this->replaceCallbacks AS $key => $callback ){
            $realKey = $this->getPlaceholderSyntax( $key ); // Simple placeholder replacement
            if( str_contains( $eventText , $realKey ) && ( $callbackResult = $callback() ) !== null && is_string( $callbackResult ) ){
                $eventText = str_replace( $realKey , $callbackResult , $eventText );
            }
        }
        return $eventText;
    }

    /**
     * @param string $key
     * @param callable $callback
     * @return void
     */
    public function addPlaceholderDecoration( string $key , callable $callback ){
        $this->replaceCallbacks[ $key ] = $callback;
    }

    /**
     * @return void
     * @throws \Exception
     */
    private function setup(): void
    {
        $filesystem = new Filesystem();
        /* @var $stashCatCompany Company|null */
        $stashCatCompany = $this->getServiceInstance( Company::class );
        /* @var $stashCatChannel Channel|null */
        $stashCatChannel = $this->getServiceInstance( Channel::class );
        /* @var $stashcatMediator StashcatMediator */
        $stashcatMediator = $this->getServiceInstance( StashcatMediator::class );
        /* @var $event Event */
        $event = $this->getServiceInstance( Event::class );
        /* @var $appService AppService */
        $appService = $this->getServiceInstance( Event::class );
        /* @var $allowedIntervals array|null */
        $allowedIntervals = $appService?->getAppConfig()?->getAllowedIntervals();

        $placeholderFile = Path::normalize( BASE_PATH . trim( $this->container->getParameter('app.message_placeholder') ) );
        if( $filesystem->exists( $placeholderFile ) ){
            $placeholderData = json_decode( file_get_contents( $placeholderFile ) , true );
            if( is_array( $placeholderData ) ){
                foreach( $placeholderData AS $key => $data ){
                    $this->addPlaceholderDecoration( $key , fn() => ( isset( $data['message'] ) && is_string( $data['message'] ) ) ? $data['message'] : null );
                }
            }
        }

        $commandContentFile = Path::normalize( BASE_PATH . trim( $this->container->getParameter('app.message_command_content') ) );
        if( $filesystem->exists( $commandContentFile ) ){
            $commandContentData = json_decode( file_get_contents( $commandContentFile ) , true );
            if( is_array( $commandContentData ) ){
                foreach( $commandContentData AS $key => $data ){
                    $this->addPlaceholderDecoration( $key , fn() => ( isset( $data['message'] ) && is_string( $data['message'] ) ) ? $data['message'] : null );
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