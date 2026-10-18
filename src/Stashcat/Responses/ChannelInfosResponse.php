<?php

namespace App\Stashcat\Responses;

use App\Stashcat\Entities\Channel;

class ChannelInfosResponse extends DefaultResponse {

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

}
