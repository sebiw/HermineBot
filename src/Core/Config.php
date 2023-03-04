<?php

namespace App\Core;

class Config extends ArrayDataCollection {

    const DEFAULT_CHANNEL_CONVERSATION_PLACEHOLDER = '[[Konversation]]';

    /**
     * @return bool
     * @throws \Exception
     */
    public function isDevMode(): bool
    {
        return boolval( $this->getResults('dev_mode') );
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function getStashcatEventCompany() : string {
        return $this->getResults('stashcat_event_company');
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function getAutoAppendToMessages() : string {
        return $this->getResults('auto_append_to_messages');
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function getAllowedChannelNames() : array {
        $allowedChannels = $this->getResults('allowed_channel');
        $allowedChannels[] = $this->getConversationChannelPlaceholderName();
        return $allowedChannels;
    }

    /**
     * @return string
     */
    public function getConversationChannelPlaceholderName() : string {
        try {
            // Try to load from Config if this name will conflict with a real channel
            return $this->getResults('conversation_channel_placeholder_name');
        } catch ( \Exception $e ){
            return self::DEFAULT_CHANNEL_CONVERSATION_PLACEHOLDER;
        }
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function getAllowedIntervals() : array {
        return $this->getResults('allowed_intervals');
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function getDateFormat() : string {
        return $this->getResults('date_format');
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function getTimeFormat() : string {
        return $this->getResults('time_format');
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function getBaseUrl() : string {
        return $this->getResults('base_url');
    }

    /**
     * @param string|null $append
     * @return string
     * @throws \Exception
     */
    public function getDataPath( ?string $append = null ) : string {
        $base = strval( $this->getResults( 'data_path' ) );
        if( !is_dir( $base ) ){
            throw new \Exception('Data-Folder does not exist!');
        } elseif( $append === null ){
            return $base;
        }

        $addSlash = substr( $base , -1) != '/';
        $path = $base . ($addSlash ? '/' : '') . $append;
        $dir = dirname( $path );
        if( !is_dir( $dir ) ){
            mkdir( $dir , 0777, true);
        }
        return $path;
    }

}