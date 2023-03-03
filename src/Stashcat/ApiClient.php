<?php

namespace App\Stashcat;

use App\Core\RestClient;
use App\Stashcat\Responses\AuthCheckResponse;
use App\Stashcat\Responses\ChannelsSubscriptedResponse;
use App\Stashcat\Responses\CompanyGroupsResponse;
use App\Stashcat\Responses\CompanyMemberResponse;
use App\Stashcat\Responses\LoginResponse;
use App\Stashcat\Responses\MessageContentResponse;
use App\Stashcat\Responses\MessageConversationsResponse;
use App\Stashcat\Responses\PrivateKeyResponse;
use App\Stashcat\Responses\SendMessageResponse;
use App\Stashcat\Responses\SuccessResponse;
use Exception;
use mysql_xdevapi\Result;

class ApiClient {

    private Config $config;

    private RestClient $restClient;

    /**
     * @param Config $config
     * @param RestClient $restClient
     */
    public function __construct( Config $config , RestClient $restClient )
    {
        $this->config = $config;
        $this->restClient = $restClient;
    }

    /**
     * @return Config
     */
    protected function getConfig(){
        return $this->config;
    }

    /**
     * @return RestClient
     */
    protected function getRestClient(){
        return $this->restClient;
    }

    /**
     * @return LoginResponse
     * @throws Exception
     */
    public function login() : LoginResponse {
        $loginData = $this->getRestClient()->post( $this->getConfig()->getAuthLoginURL() , [
            "email" => $this->getConfig()->getUserEmail(),
            "password" => $this->getConfig()->getUserPassword(),
            "device_id" => $this->getConfig()->getDeviceId(),
            "app_name" => $this->getConfig()->getAppName(),
            "encrypted" => true,
            "callable" => true,
            "key_transfer_support" => false
        ] );
        return new LoginResponse( $loginData );
    }

    /**
     * @param string $client_key
     * @return AuthCheckResponse
     * @throws Exception
     */
    public function check( string $client_key ){
        $checkData = $this->getRestClient()->post( $this->getConfig()->getAuthCheckURL() , [
            "client_key" => $client_key,
            "device_id" => $this->getConfig()->getDeviceId(),
            "encrypted" => true,
            "callable" => true,
            "key_transfer_support" => true
        ] );
        return new AuthCheckResponse( $checkData );
    }

    /**
     * @param string $client_key
     * @return PrivateKeyResponse
     * @throws Exception
     */
    public function securityGetKey( string $client_key ) : PrivateKeyResponse{
        $authCheckResult = $this->getRestClient()->post( $this->getConfig()->getSecurityPrivateKeyURL() , [
            "client_key" => $client_key,
            "device_id" => $this->getConfig()->getDeviceId()
        ]);
        return new PrivateKeyResponse( $authCheckResult );
    }

    /**
     * @param string $client_key
     * @return CompanyMemberResponse
     * @throws Exception
     */
    public function companyMembers( string $client_key ) : CompanyMemberResponse {
        $companyMemberResult = $this->getRestClient()->post( $this->getConfig()->getCompanyMemberURL() , [
            "client_key" => $client_key,
            "device_id" => $this->getConfig()->getDeviceId(),
            "no_cache" => true
        ]);
        return new CompanyMemberResponse($companyMemberResult);
    }

    /**
     * @param string $client_key
     * @param string $company_id
     * @return CompanyGroupsResponse
     * @throws Exception
     */
    public function companyGroups( string $client_key  , string $company_id ) : CompanyGroupsResponse {
        $companyGroupsResult = $this->getRestClient()->post( $this->getConfig()->getCompanyGroupsURL() , [
            "client_key" => $client_key,
            "device_id" => $this->getConfig()->getDeviceId(),
            "company" => $company_id,
            "sorting" => 'name_asc'
        ]);
        return new CompanyGroupsResponse($companyGroupsResult);
    }

    /**
     * @param string $client_key
     * @param string $company_id
     * @return ChannelsSubscriptedResponse
     * @throws Exception
     */
    public function channelsSubscripted( string $client_key , string $company_id ) : ChannelsSubscriptedResponse {
        $channelsSubscriptedResult = $this->getRestClient()->post( $this->getConfig()->getChannelsSubscriptedURL() , [
            "client_key" => $client_key,
            "device_id" => $this->getConfig()->getDeviceId(),
            "company" => $company_id
        ]);
        return new ChannelsSubscriptedResponse( $channelsSubscriptedResult );
    }

    /**
     * @param string $client_key
     * @param string $company_id
     * @param string $channel_id
     * @param string $text
     * @param string $iv
     * @param string $verification
     * @return Responses\SendMessageResponse
     * @throws Exception
     */
    public function sendMessageToChannel( string $client_key , string $company_id , string $channel_id , string $text , string $iv , string $verification = "" , ?string $metainfo = null ) : Responses\SendMessageResponse
    {
        $sendResult = $this->getRestClient()->post( $this->getConfig()->getMessageSendURL() , [
            "client_key" => $client_key,
            "device_id" => $this->getConfig()->getDeviceId(),
            "company" => $company_id,
            "target" => "channel",
            "channel_id" => $channel_id,
            "text" => $text,
            "iv" => $iv,
            "files" => "[]",
            "url" => "[]",
            "type" => "text",
            "verification" => $verification,
            "encrypted" => true,
            "metainfo" => $metainfo
        ]);
        return new Responses\SendMessageResponse( $sendResult );
    }

    /**
     * @param string $client_key
     * @param string $conversation_id
     * @param string $text
     * @param string $iv
     * @param string $verification
     * @param string|null $metainfo
     * @return SendMessageResponse
     * @throws Exception
     */
    public function sendMessageToConversation( string $client_key , string $conversation_id , string $text , string $iv , string $verification = "" , ?string $metainfo = null ) : Responses\SendMessageResponse
    {
        $sendResult = $this->getRestClient()->post( $this->getConfig()->getMessageSendURL() , [
            "client_key" => $client_key,
            "device_id" => $this->getConfig()->getDeviceId(),
            "target" => 'conversation',
            'conversation_id' => $conversation_id, // @TODO: Get Conversation-Ids...
            "text" => $text,
            "iv" => $iv,
            "files" => "[]",
            "url" => "[]",
            "type" => "text",
            "verification" => $verification,
            "encrypted" => true,
            "is_forwarded" => false,
            "metainfo" => $metainfo
        ]);
        return new Responses\SendMessageResponse( $sendResult );
    }

    /**
     * @param string $client_key
     * @return MessageConversationsResponse
     * @throws Exception
     */
    public function messageConversations( string $client_key ) : MessageConversationsResponse {
        $conversations = $this->getRestClient()->post( $this->getConfig()->getMessageConversations() , [
            "client_key" => $client_key,
            "device_id" => $this->getConfig()->getDeviceId(),
            "limit" => -1,
            "offset" => 0,
            "archive" => 0,
            "sorting" => json_encode( ["favorite_desc","last_action_desc"] )
        ]);
        return new MessageConversationsResponse( $conversations );
    }

    /**
     * @param string $client_key
     * @param string $message_id
     * @return SuccessResponse
     * @throws Exception
     */
    public function sendLikeToMessage( string $client_key , string $message_id ): SuccessResponse
    {
        $likeResult = $this->getRestClient()->post( $this->getConfig()->getMessageLikeURL() , [
            "client_key" => $client_key,
            "device_id" => $this->getConfig()->getDeviceId(),
            "message_id" => $message_id
        ]);
        return new SuccessResponse( $likeResult );
    }

    /**
     * @param string $client_key
     * @param string $channel_id
     * @return Responses\MessageContentResponse
     * @throws Exception
     */
    public function getMessagesFromChannel( string $client_key , string $channel_id , int $limit = 30 , int $offset = 0 ): Responses\MessageContentResponse
    {
        $messageContentResult = $this->getRestClient()->post( $this->getConfig()->getMessageContentURL() , [
            "client_key" => $client_key,
            "device_id" => $this->getConfig()->getDeviceId(),
            "channel_id" => $channel_id,
            "source" => "channel",
            "limit" => $limit,
            "offset" => $offset
        ]);
        return new Responses\MessageContentResponse( $messageContentResult );
    }

    /**
     * @param string $client_key
     * @param string $conversation_id
     * @param int $limit
     * @param int $offset
     * @return MessageContentResponse
     * @throws Exception
     */
    public function getMessagesFromConversation( string $client_key , string $conversation_id , int $limit = 30 , int $offset = 0 ): Responses\MessageContentResponse
    {
        $messageContentResult = $this->getRestClient()->post( $this->getConfig()->getMessageContentURL() , [
            "client_key" => $client_key,
            "device_id" => $this->getConfig()->getDeviceId(),
            "conversation_id" => $conversation_id,
            "source" => "conversation",
            "limit" => $limit,
            "offset" => $offset
        ]);
        return new Responses\MessageContentResponse( $messageContentResult );
    }

}