<?php

namespace App\Stashcat\Responses;

use App\Stashcat\Entities\Channel;

class ChannelsSubscriptedResponse extends DefaultResponse {

    /**
     * @return array|Channel[]
     * @throws \Exception
     */
    public function getChannels(): array
    {
        $channels = [];
        foreach( $this->getResults('payload' , 'channels' ) AS $channel ){
            $channels[ $channel['id'] ] = new Channel( $channel );
        }
        return $channels;
    }

    /**
     * @param string $name
     * @return array|null
     * @throws \Exception
     */
    function getChannelByName( string $name ) : ?Channel {
        foreach( $this->getChannels() AS $channel ){
            if( strtoupper( $channel->getName() ) == strtoupper( $name ) ){
                return $channel;
            }
        }
        return null;
    }

}