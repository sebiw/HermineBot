<?php

namespace App\Core;


use App\Stashcat\ApiClient;
use App\Stashcat\CryptoBox;
use App\Stashcat\Entities\Channel;
use App\Stashcat\Entities\Company;
use App\Stashcat\Entities\Message;
use App\Stashcat\Responses\CompanyMemberResponse;
use App\Stashcat\Responses\LoginResponse;

class StashcatMediator
{

    private Config $config;
    private ApiClient $stashcatApiClient;
    private CryptoBox $stashcatCryptoBox;

    private ?LoginResponse $loginResponse = null;

    private ?CompanyMemberResponse $companyMembersResponse = null;

    private array $channelsSubscripted = [];

    /**
     * @param Config $config
     * @param ApiClient $stashcatApiClient
     * @param CryptoBox $stashcatCryptoBox
     */
    public function __construct( Config $config , ApiClient $stashcatApiClient , CryptoBox $stashcatCryptoBox )
    {
        $this->config = $config;
        $this->stashcatApiClient = $stashcatApiClient;
        $this->stashcatCryptoBox = $stashcatCryptoBox;
    }

    /**
     * @return CompanyMemberResponse|null
     */
    public function getCompanyMembers(): ?CompanyMemberResponse
    {
        return $this->companyMembersResponse;
    }

    /**
     * @param string $channelName
     * @param Company $company
     * @return Channel|null
     * @throws \Exception
     */
    public function getChannelOfCompany( string $channelName , Company $company ) : ?Channel {
        if( isset( $this->channelsSubscripted[ $company->getId() ] ) ){
            return $this->channelsSubscripted[ $company->getId() ]->getChannelByName( $channelName );
        }
        throw new \Exception('Company not found!');
    }

    /**
     * @return Config
     */
    protected function getAppConfig(): Config
    {
        return $this->config;
    }

    /**
     * @return ApiClient
     */
    protected function getStashcatApiClient(): ApiClient
    {
        return $this->stashcatApiClient;
    }

    /**
     * @return CryptoBox
     */
    protected function getStashcatCryptoBox(): CryptoBox
    {
        return $this->stashcatCryptoBox;
    }

    /**
     * @return LoginResponse|null
     */
    protected function getLoginResponse(): ?LoginResponse
    {
        return $this->loginResponse;
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function login(): bool
    {
        $filename = $this->getAppConfig()->getDataPath("credentials/login-data.json");
        if( !file_exists( $filename ) ){
            // Login if not sored...
            $this->loginResponse = $this->getStashcatApiClient()->login();
            file_put_contents($filename , json_encode( $this->loginResponse , JSON_PRETTY_PRINT ) );
        } else {
            // Reload from cache
            $this->loginResponse = new \App\Stashcat\Responses\LoginResponse( json_decode( file_get_contents( $filename ) , true ) );
        }

        // Auth Check - login still valid?=
        $authCheckResponse = $this->getStashcatApiClient()->check( $this->loginResponse->getClientKey() );
        if( !$authCheckResponse->success() ){
            // if not valid - login and store results
            $this->loginResponse = $this->getStashcatApiClient()->login();
            file_put_contents($filename , json_encode( $this->loginResponse , JSON_PRETTY_PRINT ) );
        }
        return $this->loginResponse->isValid();
    }

    /**
     * Private key decryption
     * @return void
     * @throws \Exception
     */
    public function decryptPrivateKey(){
        $securityResponse = $this->getStashcatApiClient()->securityGetKey( $this->getLoginResponse()->getClientKey() );
        $this->getStashcatCryptoBox()->setPrivateKeyFromResponse( $securityResponse );
    }

    /**
     * @param Channel $channel
     * @return void
     * @throws \Exception
     */
    public function decryptChannelPrivateKey( Channel $channel ){
        $this->getStashcatCryptoBox()->setChannelEncryptionChannel( $channel->getKey() , $channel->getId() );
    }

    /**
     * @return void
     * @throws \Exception
     */
    public function loadCompanies(){
        $this->companyMembersResponse = $this->getStashcatApiClient()->companyMembers( $this->getLoginResponse()->getClientKey() );
    }

    /**
     * @param Company $company
     * @return void
     * @throws \Exception
     */
    public function loadChannelSubscripted( Company $company ){
        $this->channelsSubscripted[ $company->getId() ] = $this->getStashcatApiClient()->channelsSubscripted( $this->getLoginResponse()->getClientKey() , $company->getId() );
    }

    /**
     * @param string $plainMessage
     * @param Channel $channel
     * @return \App\Stashcat\Responses\SendMessageResponse
     * @throws \Exception
     */
    public function sendMessageToChannel( string $plainMessage , Channel $channel ): \App\Stashcat\Responses\SendMessageResponse
    {
        $ivMessage = "";
        $encryptedMessage = $this->getStashcatCryptoBox()->getChannelMessageEncrypted( $plainMessage , $channel->getId() , $ivMessage );
        return $this->getStashcatApiClient()->sendMessageToChannel( $this->getLoginResponse()->getClientKey() , $channel->getCompanyId() , $channel->getId() , $encryptedMessage , $ivMessage );
    }

    /**
     * @param Channel $channel
     * @param int $limit
     * @param int $offset
     * @return array|Message[]
     * @throws \Exception
     */
    public function getMessagesFromChannel( Channel $channel , int $limit = 30 , int $offset = 0 ): array
    {
        // get Messages...
        $messages = [];
        $messageContentResult = $this->getStashcatApiClient()->getMessagesFromChannel( $this->getLoginResponse()->getClientKey() , $channel->getId() , $limit , $offset );
        // Encrypt messages...
        foreach( $messageContentResult->getMessages() AS $message ){
            $messageDecryptResult = $this->getStashcatCryptoBox()->getChannelMessageDecrypted( $message , $channel->getId() );
            $messages[] = $message->toDecrypted( $messageDecryptResult );
        }
        return $messages;
    }

    /**
     * @param Message $message
     * @return \App\Stashcat\Responses\SuccessResponse|null
     * @throws \Exception
     */
    public function likeMessage( Message $message ): ?\App\Stashcat\Responses\SuccessResponse
    {
        if( !$message->isLiked() ){
            return $this->getStashcatApiClient()->sendLikeToMessage( $this->getLoginResponse()->getClientKey() , $message->getId() );
        }
        return null;
    }



}