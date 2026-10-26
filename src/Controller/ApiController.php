<?php

namespace App\Controller;

use App\Core\DatabaseLogger;
use App\Service\AppService;
use App\Stashcat\Entities\User as StashcatUser;
use App\Entity\User;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[IsGranted('ROLE_API')]
class ApiController extends AbstractController
{
    #[Route('/api/send-message', name: 'api_send_message', methods: ['POST'])]
    public function apiSendMessage( KernelInterface $kernel, Request $request, #[CurrentUser] UserInterface $user , #[Autowire(service: 'logger.events')] DatabaseLogger $logger ): Response
    {
        $application = new Application( $kernel );
        $application->setAutoExit(false);

        $content = json_decode( $request->getContent() , true );

        $channel = $request->query->get('channel') ?? $request->request->get('channel') ?? $content['channel'] ?? null;
        $conversationId = $request->query->get('conversationId') ?? $request->request->get('conversationId') ?? $content['conversationId'] ?? null;
        $message = $request->query->get('message') ?? $request->request->get('message') ?? $content['message'] ?? null;

        if( empty( $message ) ){
            return $this->errorResponse('Message missing!' , Response::HTTP_BAD_REQUEST );
        }

        if( !empty( $channel ) )
        {
            $input = new ArrayInput([
                'command' => 'hermine:send-message',
                '--context' => 'REST',
                '--channel' => $channel,
                '--message' => $message
            ]);
        }
        else if( !empty( $conversationId ) )
        {
            $input = new ArrayInput([
                'command' => 'hermine:send-message',
                '--context' => 'REST',
                '--conversationId' => $conversationId,
                '--message' => $message
            ]);
        }
        else
        {
            return $this->errorResponse('channel or conversationId missing!' , Response::HTTP_BAD_REQUEST );
        }

        // You can use NullOutput() if you don't need the output
        $output = new BufferedOutput();
        $exitCode = $application->run($input, $output);
        // Ausgabe nur einmal lesen – fetch() leert den Puffer
        $commandOutput = $output->fetch();

        if( $exitCode === Command::SUCCESS ){
            $logger->info(sprintf('Message sent via REST to %s' , $channel ?? $conversationId ) );
            return new JsonResponse([
                'status' => 'OK',
                'content' => $commandOutput
            ]);
        }

        $logger->emergency(sprintf('Message sent via REST to %s FAILED' , $channel ?? $conversationId ), ['error' => $commandOutput] );
        // Stacktrace nur ins Log, nicht an den Aufrufer
        return new JsonResponse([
            'status' => 'ERROR',
            'content' => trim( preg_replace( '/\n\s*Exception trace:.*$/s' , '' , $commandOutput ) )
        ], Response::HTTP_INTERNAL_SERVER_ERROR );
    }

    /**
     * Mitglieder eines Channels (nur lesend) – z. B. für Abfragen im StAN-Manager. Nur Channels aus allowed_channel,
     * nicht der Unterhaltungs-Platzhalter. Antwort: status, channel (id, name), ownUserId (der Account des Bots), members.
     */
    #[Route('/api/channel-members', name: 'api_channel_members', methods: ['POST'])]
    public function apiChannelMembers( Request $request , AppService $app , #[Autowire(service: 'logger.events')] DatabaseLogger $logger ): Response
    {
        $content = json_decode( $request->getContent() , true );
        $channelName = trim( strval( is_array( $content ) ? ( $content['channel'] ?? '' ) : '' ) );
        if( $channelName === '' ){
            return $this->errorResponse('channel missing!' , Response::HTTP_BAD_REQUEST );
        }
        $config = $app->getAppConfig();
        if( $channelName === $config->getConversationChannelPlaceholderName() || !in_array( $channelName , $config->getAllowedChannelNames() , true ) ){
            return $this->errorResponse('Channel not allowed!' , Response::HTTP_FORBIDDEN );
        }

        try {
            $mediator = $app->getMediator();
            if( !$mediator->login() ){
                throw new \Exception('Login failed!');
            }
            $mediator->loadCompanies();
            $company = $mediator->getCompanyMembers()->getCompanyByName( $config->getStashcatEventCompany() );
            $mediator->loadChannelSubscripted( $company );
            if( ( $channel = $mediator->getChannelOfCompany( $channelName , $company ) ) === null ){
                return $this->errorResponse('Channel not found!' , Response::HTTP_NOT_FOUND );
            }
            $members = array_values( array_map( [ self::class , 'memberData' ] , $mediator->getChannelMembers( $channel ) ) );
        } catch( \Throwable $e ){
            $logger->error( sprintf('Loading channel members via REST failed (%s)' , $channelName ) , [ 'error' => $e->getMessage() ] );
            return $this->errorResponse('Loading channel members failed!' , Response::HTTP_INTERNAL_SERVER_ERROR );
        }

        $logger->info( sprintf('Channel members sent via REST (%s, %d)' , $channelName , count( $members ) ) );
        return new JsonResponse([
            'status' => 'OK',
            'channel' => [ 'id' => strval( $channel->getId() ) , 'name' => $channelName ],
            'ownUserId' => $mediator->getOwnUserId(),
            'members' => $members
        ]);
    }

    /**
     * Persönliche Nachricht (z. B. PIN für die Dienst-Abfragen des StAN-Managers) in die bestehende 1:1-Unterhaltung mit
     * einem Mitglied eines freigegebenen Channels. Nie an den eigenen Account, legt keine Unterhaltung an. Der Text
     * kommt nicht ins Log (kann eine PIN enthalten). Antwort: status, conversationId.
     */
    #[Route('/api/direct-message', name: 'api_direct_message', methods: ['POST'])]
    public function apiDirectMessage( Request $request , AppService $app , #[Autowire(service: 'logger.events')] DatabaseLogger $logger ): Response
    {
        $content = json_decode( $request->getContent() , true );
        $content = is_array( $content ) ? $content : [];
        $userId = trim( strval( $content['userId'] ?? '' ) );
        $channelName = trim( strval( $content['channel'] ?? '' ) );
        $message = trim( strval( $content['message'] ?? '' ) );
        if( $userId === '' || $channelName === '' || $message === '' ){
            return $this->errorResponse('userId, channel or message missing!' , Response::HTTP_BAD_REQUEST );
        }
        $config = $app->getAppConfig();
        if( $channelName === $config->getConversationChannelPlaceholderName() || !in_array( $channelName , $config->getAllowedChannelNames() , true ) ){
            return $this->errorResponse('Channel not allowed!' , Response::HTTP_FORBIDDEN );
        }

        try {
            $mediator = $app->getMediator();
            if( !$mediator->login() ){
                throw new \Exception('Login failed!');
            }
            if( $userId === $mediator->getOwnUserId() ){
                return $this->errorResponse('Not to the own account!' , Response::HTTP_FORBIDDEN );
            }
            $mediator->decryptPrivateKey();
            $mediator->loadCompanies();
            $company = $mediator->getCompanyMembers()->getCompanyByName( $config->getStashcatEventCompany() );
            $mediator->loadChannelSubscripted( $company );
            if( ( $channel = $mediator->getChannelOfCompany( $channelName , $company ) ) === null ){
                return $this->errorResponse('Channel not found!' , Response::HTTP_NOT_FOUND );
            }
            $memberIds = array_map( fn( StashcatUser $member ) => strval( $member->getId() ) , $mediator->getChannelMembers( $channel ) );
            if( !in_array( $userId , $memberIds , true ) ){
                return $this->errorResponse('User is not a member of the channel!' , Response::HTTP_FORBIDDEN );
            }
            if( ( $conversation = $mediator->findDirectConversation( $userId ) ) === null ){
                return $this->errorResponse('No conversation with this user yet – please start one by hand once.' , Response::HTTP_NOT_FOUND );
            }
            $mediator->decryptConversationPrivateKey( $conversation );
            $result = $mediator->sendMessageToConversation( $message . $config->getAutoAppendToMessages() , $conversation );
            if( $result->_getStatusValue() != 'OK' ){
                throw new \Exception( sprintf('Sending message failed, status: %s' , $result->_getStatusValue() ) );
            }
        } catch( \Throwable $e ){
            $logger->error( sprintf('Direct message via REST failed (user %s)' , $userId ) , [ 'error' => $e->getMessage() ] );
            return $this->errorResponse('Sending the direct message failed!' , Response::HTTP_INTERNAL_SERVER_ERROR );
        }

        $logger->info( sprintf('Direct message sent via REST (user %s, conversation %s)' , $userId , $conversation->getId() ) );
        return new JsonResponse([
            'status' => 'OK',
            'conversationId' => strval( $conversation->getId() )
        ]);
    }

    /**
     * @return array{id: string, first_name: string, last_name: string, department: string|null, display_name: string}
     */
    public static function memberData( StashcatUser $user ): array
    {
        return [ 'id' => strval( $user->getId() ) ] + $user->getNameParts() + [ 'display_name' => $user->getDisplayName() ];
    }

    /**
     * @param string $message
     * @param int $statusCode
     * @return JsonResponse
     */
    protected function errorResponse( string $message , int $statusCode ): JsonResponse
    {
        return new JsonResponse([
            'status' => 'ERROR',
            'message' => $message
        ], $statusCode );
    }


}