<?php

namespace App\Stashcat;

class Config {

    // General...
    protected string $baseUrl = "";
    protected string $version = "";
    protected string $appNamePattern = "";
    protected string $deviceId = '';

    // Login...
    protected string $userEmail = "";
    protected string $userPassword = "";
    protected string $userPassphrase = "";

    // Cipher-Config...
    protected string $cipherAlgo = "aes-256-cbc";
    protected int $cipherAlgoPaddingBlockSize = 16;
    protected int $rsaOaepPadding = OPENSSL_PKCS1_OAEP_PADDING;
    protected int $aesPadding = OPENSSL_ZERO_PADDING | OPENSSL_RAW_DATA;


    // URL Path...
    protected string $urlPath_login = "auth/login";
    protected string $urlPath_security_privat_key = "security/get_private_key";
    protected string $urlPath_channels_subscripted = "channels/subscripted";
    protected string $urlPath_message_content = "message/content";
    protected string $urlPath_message_send = "message/send";
    protected string $urlPath_message_conversations = "message/conversations";
    protected string $urlPath_message_like = "message/like";
    protected string $urlPath_auth_check = "auth/check";
    protected string $urlPath_company_member = "company/member";
    protected string $urlPath_company_groups = 'groups/get_company_groups';
    // Aus einem Mitschnitt des Web-Clients ermittelt (2026-10-18), nur lesend
    protected string $urlPath_channels_members = 'channels/members';
    protected string $urlPath_channels_infos = 'channels/infos';
    protected string $urlPath_users_listing = 'users/listing';
    protected string $urlPath_users_info = 'users/info';
    protected string $urlPath_message_conversation = 'message/conversation';
    protected string $urlPath_message_seen_users = 'message/list_message_seen_users';
    protected string $urlPath_message_seen_users_count = 'message/get_message_seen_users_count';


    /**
     * @param array $config
     * @throws \Exception
     */
    public function __construct( array $config )
    {
        foreach( $config AS $key => $value ){
            if( property_exists( $this , $key ) ){
                switch ( true ){
                    case ($key == 'deviceId' && strlen( $value ) !== 32) : throw new \Exception(sprintf('%s needs to be 32 chars long!' , $key) );
                }
                $this->$key = $value;
            }
        }
    }

    /**
     * @return array|string|string[]
     */
    public function getAppName()
    {
        return str_replace('{version}' , $this->getVersion() , $this->getAppNamePattern() );
    }

    /**
     * @param $append
     * @return string
     */
    protected function addToBaseURL( $append ) : string {
        $addSlash = substr( $this->getBaseUrl() , -1) != '/';
        return $this->getBaseUrl() . ($addSlash ? '/' : '') . $append;
    }

    /**
     * @return string
     */
    public function getAuthLoginURL(): string
    {
        return $this->addToBaseURL( $this->urlPath_login );
    }

    /**
     * @return string
     */
    public function getSecurityPrivateKeyURL(): string
    {
        return $this->addToBaseURL( $this->urlPath_security_privat_key );
    }

    /**
     * @return string
     */
    public function getChannelsSubscriptedURL(): string
    {
        return $this->addToBaseURL( $this->urlPath_channels_subscripted );
    }

    /**
     * @return string
     */
    public function getMessageContentURL(): string
    {
        return $this->addToBaseURL( $this->urlPath_message_content );
    }

    /**
     * @return string
     */
    public function getMessageSendURL(): string
    {
        return $this->addToBaseURL( $this->urlPath_message_send );
    }

    /**
     * @return string
     */
    public function getMessageLikeURL() : string {
        return $this->addToBaseURL( $this->urlPath_message_like );
    }

    /**
     * @return string
     */
    public function getAuthCheckURL(): string
    {
        return $this->addToBaseURL( $this->urlPath_auth_check );
    }

    /**
     * @return string
     */
    public function getCompanyMemberURL(): string
    {
        return $this->addToBaseURL( $this->urlPath_company_member );
    }

    /**
     * @return string
     */
    public function getCompanyGroupsURL(): string
    {
        return $this->addToBaseURL( $this->urlPath_company_groups );
    }

    /**
     * @return string
     */
    public function getMessageConversations() : string {
        return $this->addToBaseURL( $this->urlPath_message_conversations );
    }

    /**
     * @return string
     */
    public function getChannelsMembersURL() : string {
        return $this->addToBaseURL( $this->urlPath_channels_members );
    }

    /**
     * @return string
     */
    public function getChannelsInfosURL() : string {
        return $this->addToBaseURL( $this->urlPath_channels_infos );
    }

    /**
     * @return string
     */
    public function getUsersListingURL() : string {
        return $this->addToBaseURL( $this->urlPath_users_listing );
    }

    /**
     * @return string
     */
    public function getUsersInfoURL() : string {
        return $this->addToBaseURL( $this->urlPath_users_info );
    }

    /**
     * @return string
     */
    public function getMessageConversationURL() : string {
        return $this->addToBaseURL( $this->urlPath_message_conversation );
    }

    /**
     * @return string
     */
    public function getMessageSeenUsersURL() : string {
        return $this->addToBaseURL( $this->urlPath_message_seen_users );
    }

    /**
     * @return string
     */
    public function getMessageSeenUsersCountURL() : string {
        return $this->addToBaseURL( $this->urlPath_message_seen_users_count );
    }

    /**
     * @return string
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * @return string
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * @return string
     */
    public function getAppNamePattern(): string
    {
        return $this->appNamePattern;
    }

    /**
     * @return string
     */
    public function getCipherAlgo(): string
    {
        return $this->cipherAlgo;
    }

    /**
     * @return int
     */
    public function getCipherAlgoPaddingBlockSize(): int
    {
        return $this->cipherAlgoPaddingBlockSize;
    }

    /**
     * @return int
     */
    public function getRsaOaepPadding(): int
    {
        return $this->rsaOaepPadding;
    }

    /**
     * @return string
     */
    public function getUserEmail(): string
    {
        return $this->userEmail;
    }

    /**
     * @return string
     */
    public function getUserPassword(): string
    {
        return $this->userPassword;
    }

    /**
     * @return string
     */
    public function getUserPassphrase(): string
    {
        return $this->userPassphrase;
    }

    /**
     * Warning: Needs to be 32 chars long!
     * @return string
     */
    public function getDeviceId(): string
    {
        return $this->deviceId;
    }

    /**
     * @return int|string
     */
    public function getAesPadding()
    {
        return $this->aesPadding;
    }


}