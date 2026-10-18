<?php

namespace App\Controller;

use App\Core\DatabaseLogger;
use App\Entity\User;
use Doctrine\Persistence\ManagerRegistry;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
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

        $channel = $request->get('channel') ?? $content['channel'] ?? null;
        $conversationId = $request->get('conversationId') ?? $content['conversationId'] ?? null;
        $message = $request->get('message') ?? $content['message'] ?? null;

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
        return new JsonResponse([
            'status' => 'ERROR',
            'content' => $commandOutput
        ], Response::HTTP_INTERNAL_SERVER_ERROR );
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