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
                    $this->addCommandDecoration( $key , function() use ($data){
                        if( isset( $data['url'] ) && isset( $data['payload'] ) ){
                            $rest = new RestClient( RestClient::RESPONSE_FORMAT_JSON );
                            $result = $rest->post( $data['url'] , json_encode( $data['payload'] ) , null , [ 'Content-Type' => 'application/json'] );
                            return $result['content'] ?? $result['message'] ?? null;
                        }
                        return null;
                    } );
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