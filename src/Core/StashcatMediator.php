<?php

namespace App\Core;


use App\Stashcat\ApiClient;
use App\Stashcat\CryptoBox;
use App\Stashcat\Entities\Channel;
use App\Stashcat\Entities\Company;
use App\Stashcat\Entities\Conversation;
use App\Stashcat\Entities\Group;
use App\Stashcat\Entities\Message;
use App\Stashcat\Entities\MessageSeenUser;
use App\Stashcat\Entities\User;
use App\Stashcat\Responses\CompanyGroupsResponse;
use App\Stashcat\Responses\CompanyMemberResponse;
use App\Stashcat\Responses\LoginResponse;
use App\Stashcat\Responses\SendMessageResponse;

class StashcatMediator
{

    private Config $config;
    private ApiClient $stashcatApiClient;
    private CryptoBox $stashcatCryptoBox;

    private ?LoginResponse $loginResponse = null;

    private ?CompanyMemberResponse $companyMembersResponse = null;
    private array $companyGroups = [];
    private array $channelsSubscripted = [];

    private array $conversations = [];

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
     * @param string $groupName
     * @param Company $company
     * @return Group|null
     * @throws \Exception
     */
    public function getGroupOfCompany( string $groupName , Company $company ): ?Group
    {
        if( isset( $this->companyGroups[ $company->getId() ] ) ){
            /* @var $companyGroups CompanyGroupsResponse */
            $companyGroups = $this->companyGroups[ $company->getId() ];
            return $companyGroups->getGroupByName( $groupName);
        }
        throw new \Exception('Company not found!');
    }

    /**
     * @param Company $company
     * @return Group[]
     * @throws \Exception
     */
    public function getGroupsOfCompany( Company $company ): array
    {
        if( isset( $this->companyGroups[ $company->getId() ] ) ){
            /* @var $companyGroups CompanyGroupsResponse */
            $companyGroups = $this->companyGroups[ $company->getId() ];
            return $companyGroups->getGroups();
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
     * Privaten Signaturschlüssel laden (nötig zum Anlegen von Unterhaltungen), setzt decryptPrivateKey() voraus
     * @return void
     * @throws \Exception
     */
    public function decryptPrivateSigningKey(){
        $signingKeyResponse = $this->getStashcatApiClient()->securityGetSigningKey( $this->getLoginResponse()->getClientKey() );
        $this->getStashcatCryptoBox()->setPrivateSigningKeyFromResponse( $signingKeyResponse );
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
     * @param Conversation $conversation
     * @return void
     * @throws \Exception
     */
    public function decryptConversationPrivateKey( Conversation $conversation ){
        $this->getStashcatCryptoBox()->setConversationEncryptionConversation( $conversation->getKey() , $conversation->getId() );
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
    public function loadGroups( Company $company ){
        $this->companyGroups[ $company->getId() ] = $this->getStashcatApiClient()->companyGroups( $this->getLoginResponse()->getClientKey() , $company->getId() );
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
     * @return void
     * @throws \Exception
     */
    public function loadConversations(): void
    {
        $this->conversations = $this->getStashcatApiClient()->messageConversations( $this->getLoginResponse()->getClientKey() )->getConversations();
    }

    /**
     * @param $id
     * @return Conversation|null
     */
    public function getConversationById( $id ) : ?Conversation{
        /* @var $conversation Conversation */
        foreach( $this->conversations AS $conversation ){
            if( $conversation->getId() === $id ){
                return $conversation;
            }
        }
        return null;
    }

    /**
     * @return array|Conversation[]
     */
    public function getLoadedConversations(): array
    {
        return $this->conversations;
    }

    /**
     * Neue Unterhaltung mit den angegebenen Personen anlegen (eigener Account wird automatisch ergänzt).
     * Ablauf wie im Web-Client: zufälliger Schlüssel, für jedes Mitglied verschlüsselt und signiert.
     * Setzt login() und decryptPrivateKey() voraus; der Signaturschlüssel wird bei Bedarf nachgeladen.
     * @param array $userIds
     * @param int $validity Gültigkeit der Schlüssel-Signaturen für die anderen Mitglieder in Sekunden (Web-Client: 30 Tage)
     * @param bool $allowUnverified auch an Personen, deren Schlüssel sich nicht prüfen lassen (Web-Client fragt „Trotzdem fortfahren?“)
     * @return Conversation
     * @throws \Exception
     */
    public function createConversation( array $userIds , int $validity = 2592000 , bool $allowUnverified = false ): Conversation
    {
        if( !$this->getStashcatCryptoBox()->hasPrivateSigningKey() ){
            $this->decryptPrivateSigningKey();
        }

        $ownUserId = (string) $this->getLoginResponse()->getUserId();
        $ownPublicKey = $this->getLoginResponse()->getUserPublicKey();

        $userIds = array_values( array_unique( array_filter( array_map( 'strval' , $userIds ) , fn( $userId ) => $userId !== '' && $userId !== $ownUserId ) ) );
        if( empty( $userIds ) ){
            throw new \Exception('No members for the conversation!');
        }

        // Schlüssel der Mitglieder laden und prüfen
        $users = [];
        $unverified = [];
        foreach( $userIds AS $userId ){
            $user = $this->getUserInfo( $userId , true );
            if( empty( $user->getPublicKey() ) ){
                throw new \Exception( sprintf( 'User %s has no public key!' , $userId ) );
            }
            $verified = $this->getStashcatCryptoBox()->isUserKeyVerified( $user );
            if( !$verified ){
                $unverified[] = $userId;
            }
            $users[ $userId ] = [ 'publicKey' => $user->getPublicKey() , 'verified' => $verified ];
        }
        if( !empty( $unverified ) && !$allowUnverified ){
            throw new \Exception( sprintf( 'Keys of users %s could not be verified!' , implode( ', ' , $unverified ) ) );
        }

        $conversationKey = $this->getStashcatCryptoBox()->getRandomConversationKey();
        $uniqueIdentifier = Crypt::randomString( 32 );
        $memberIds = array_merge( $userIds , [ $ownUserId ] );
        $expiry = time() + $validity;

        $members = [];
        foreach( $users AS $userId => $user ){
            $members[] = $this->getStashcatCryptoBox()->getConversationMemberKey( $conversationKey , $userId , $user['publicKey'] , $uniqueIdentifier , $memberIds , $expiry , $user['verified'] );
        }
        // Eigener Schlüssel ohne Ablaufdatum
        $members[] = $this->getStashcatCryptoBox()->getConversationMemberKey( $conversationKey , $ownUserId , $ownPublicKey , $uniqueIdentifier , $memberIds , null , true );

        $response = $this->getStashcatApiClient()->createEncryptedConversation( $this->getLoginResponse()->getClientKey() , $members , $uniqueIdentifier );
        if( !$response->isValid() ){
            throw new \Exception( sprintf( 'Conversation could not be created: %s' , $response->getErrorMessage() ) );
        }

        $conversation = $response->getConversation();
        $this->getStashcatCryptoBox()->setConversationKey( $conversationKey , $conversation->getId() );
        $this->conversations[] = $conversation;
        return $conversation;
    }

    /**
     * @param string $plainMessage
     * @param Channel $channel
     * @return \App\Stashcat\Responses\SendMessageResponse
     * @throws \Exception
     */
    public function sendMessageToChannel( string $plainMessage , Channel $channel ): \App\Stashcat\Responses\SendMessageResponse
    {
        $metainfo = null;
        $ivMessage = "";
        $verification = ""; // @TODO: Verification! Seems to be: MD5 of "Text" + deviceId + microTime + location longitude + location latitude + timestamp, but its never used???

        // simple markdown check... maybe changing in the future!
        $markdown = preg_match_all("#[*].*[*]#" , $plainMessage ) || preg_match_all("#_.*_#" , $plainMessage );
        if( $markdown ){
            $metainfo = json_encode([ "v" => 1 , "style" => "md" ]);
        }

        $encryptedMessage = $this->getStashcatCryptoBox()->getChannelMessageEncrypted( $plainMessage , $channel->getId() , $ivMessage );
        return $this->getStashcatApiClient()->sendMessageToChannel( $this->getLoginResponse()->getClientKey() , $channel->getCompanyId() , $channel->getId() , $encryptedMessage , $ivMessage , $verification , $metainfo );
    }

    /**
     * @param string $plainMessage
     * @param Conversation $conversation
     * @return SendMessageResponse
     * @throws \Exception
     */
    public function sendMessageToConversation( string $plainMessage , Conversation $conversation ): \App\Stashcat\Responses\SendMessageResponse
    {
        $metainfo = null;
        $ivMessage = "";
        $verification = ""; // @TODO: Verification! Seems to be: MD5 of "Text" + deviceId + microTime + location longitude + location latitude + timestamp, but its never used???

        // simple markdown check... maybe changing in the future!
        // @TODO: Check markdown support for conversations?
        $markdown = preg_match_all("#[*].*[*]#" , $plainMessage ) || preg_match_all("#_.*_#" , $plainMessage );
        if( $markdown ){
            $metainfo = json_encode([ "v" => 1 , "style" => "md" ]);
        }

        $encryptedMessage = $this->getStashcatCryptoBox()->getConversationMessageEncrypted( $plainMessage , $conversation->getId() , $ivMessage );

        return $this->getStashcatApiClient()->sendMessageToConversation( $this->getLoginResponse()->getClientKey() , $conversation->getId() , $encryptedMessage , $ivMessage , $verification , $metainfo );
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
     * @param Conversation $conversation
     * @param int $limit
     * @param int $offset
     * @return array|Message[]
     * @throws \Exception
     */
    public function getMessagesFromConversation( Conversation $conversation , int $limit = 30 , int $offset = 0 ): array
    {
        // get Messages...
        $messages = [];
        $messageContentResult = $this->getStashcatApiClient()->getMessagesFromConversation( $this->getLoginResponse()->getClientKey() , $conversation->getId() , $limit , $offset );
        // Encrypt messages...
        foreach( $messageContentResult->getMessages() AS $message ){
            $messageDecryptResult = $this->getStashcatCryptoBox()->getConversationMessageDecrypted( $message , $conversation->getId() );
            $messages[] = $message->toDecrypted( $messageDecryptResult );
        }
        return $messages;
    }

    /**
     * Alle Mitglieder eines Channels (lädt alle Seiten)
     * @param Channel $channel
     * @param int $pageSize
     * @return array|User[]
     * @throws \Exception
     */
    public function getChannelMembers( Channel $channel , int $pageSize = 40 ): array
    {
        $members = [];
        $offset = 0;
        do {
            $page = $this->getStashcatApiClient()->channelMembers( $this->getLoginResponse()->getClientKey() , $channel->getId() , $pageSize , $offset )->getMembers();
            $members += $page;
            $offset += $pageSize;
        } while( count( $page ) === $pageSize );
        return $members;
    }

    /**
     * Aktuelle Daten einzelner Channels laden (z. B. Mitgliederzahl, letzte Aktivität)
     * @param array $channelIds
     * @return array|Channel[]
     * @throws \Exception
     */
    public function getChannelInfos( array $channelIds ): array
    {
        return $this->getStashcatApiClient()->channelInfos( $this->getLoginResponse()->getClientKey() , $channelIds )->getChannels();
    }

    /**
     * Personen suchen bzw. nach Gruppen auflisten (eine Seite)
     * @param string $search
     * @param array $groupIds
     * @param int $limit
     * @param int $offset
     * @return array|User[]
     * @throws \Exception
     */
    public function searchUsers( string $search = '' , array $groupIds = [] , int $limit = 50 , int $offset = 0 ): array
    {
        return $this->getStashcatApiClient()->usersListing( $this->getLoginResponse()->getClientKey() , $search , $groupIds , [] , $limit , $offset )->getUsers();
    }

    /**
     * @param string $userId
     * @param bool $withKey
     * @return User
     * @throws \Exception
     */
    public function getUserInfo( string $userId , bool $withKey = false ): User
    {
        return $this->getStashcatApiClient()->userInfo( $this->getLoginResponse()->getClientKey() , $userId , $withKey )->getUser();
    }

    /**
     * Eine Unterhaltung direkt laden (ohne die komplette Liste)
     * @param string $conversationId
     * @return Conversation
     * @throws \Exception
     */
    public function loadConversation( string $conversationId ): Conversation
    {
        return $this->getStashcatApiClient()->conversation( $this->getLoginResponse()->getClientKey() , $conversationId )->getConversation();
    }

    /**
     * Wer hat eine Nachricht gesehen – laut Erfahrung nicht zuverlässig, nicht für Auswertungen nutzen
     * @param Message $message
     * @return array|MessageSeenUser[]
     * @throws \Exception
     */
    public function getMessageSeenUsers( Message $message ): array
    {
        return $this->getStashcatApiClient()->messageSeenUsers( $this->getLoginResponse()->getClientKey() , $message->getId() )->getSeenUsers();
    }

    /**
     * @param Message $message
     * @return int
     * @throws \Exception
     */
    public function getMessageSeenCount( Message $message ): int
    {
        return $this->getStashcatApiClient()->messageSeenUsersCount( $this->getLoginResponse()->getClientKey() , $message->getId() )->getCount();
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