<?php

namespace App\Controller;

use App\Core\UserManager;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Nutzer des Bots verwalten: Administratoren der Oberfläche und API-Nutzer für REST (z. B. StAN-Manager).
 * Der Schlüssel eines API-Nutzers wird nur einmal angezeigt (Flash „key“), gespeichert ist nur der Hash.
 */
#[IsGranted('ROLE_ADMIN')]
class UserController extends AbstractController
{
    use CsrfTokenCheck;

    #[Route('/users', name: 'users', methods: [ 'GET' ])]
    public function list( UserManager $userManager ): Response
    {
        return $this->render('default/users.html.twig' , [
            'users' => $userManager->all(),
            'kinds' => UserManager::KINDS,
        ]);
    }

    #[Route('/users/new', name: 'users_new', methods: [ 'GET' ])]
    public function new(): Response
    {
        return $this->renderNew( [ 'username' => '' , 'kind' => UserManager::KIND_API ] );
    }

    /**
     * @param array{username: string, kind: string} $form
     */
    private function renderNew( array $form ): Response
    {
        return $this->render('default/user.new.html.twig' , [
            'form' => $form,
            'kinds' => UserManager::KINDS,
            'passwordMinLength' => UserManager::PASSWORD_MIN_LENGTH,
        ]);
    }

    #[Route('/users', name: 'users_create', methods: [ 'POST' ])]
    public function create( Request $request , UserManager $userManager , #[CurrentUser] User $current ): Response
    {
        $this->denyAccessUnlessCsrfTokenValid( 'users' , $request );
        $form = [ 'username' => $request->request->getString('username') , 'kind' => $request->request->getString('kind') ];
        try {
            [ $user , $key ] = $userManager->create( $request->request->getString('username') , $request->request->getString('kind') , $request->request->getString('password') , $current->getUserIdentifier() );
        } catch( \InvalidArgumentException $exception ){
            // Eingaben bleiben stehen, das Passwort wird neu eingegeben
            $this->addFlash( 'error' , $exception->getMessage() );
            return $this->renderNew( $form );
        }
        $this->addFlash( 'success' , sprintf( 'Nutzer „%s“ angelegt.' , $user->getUserIdentifier() ) );
        if( $key !== null ){
            $this->addFlash( 'key' , $key );
        }
        return $this->redirectToRoute('user_edit' , [ 'id' => $user->getId() ] );
    }

    #[Route('/users/{id}', name: 'user_edit', requirements: [ 'id' => '\d+' ], methods: [ 'GET' ])]
    public function edit( int $id , Request $request , UserRepository $userRepository , #[CurrentUser] User $current ): Response
    {
        $user = $userRepository->find( $id ) ?? throw $this->createNotFoundException();
        return $this->render('default/user.edit.html.twig' , [
            'user' => $user,
            'kind' => UserManager::kindOf( $user ),
            'kinds' => UserManager::KINDS,
            'isSelf' => $user === $current,
            'newKeys' => $request->getSession() instanceof FlashBagAwareSessionInterface ? $request->getSession()->getFlashBag()->get('key') : [],
            'passwordMinLength' => UserManager::PASSWORD_MIN_LENGTH,
        ]);
    }

    #[Route('/users/{id}', name: 'user_save', requirements: [ 'id' => '\d+' ], methods: [ 'POST' ])]
    public function save( int $id , Request $request , UserRepository $userRepository , UserManager $userManager , #[CurrentUser] User $current ): Response
    {
        $user = $userRepository->find( $id ) ?? throw $this->createNotFoundException();
        $this->denyAccessUnlessCsrfTokenValid( 'user-' . $id , $request );
        try {
            if( $request->request->has('delete') ){
                $name = $user->getUserIdentifier();
                $userManager->delete( $user , $current );
                $this->addFlash( 'success' , sprintf( 'Nutzer „%s“ gelöscht.' , $name ) );
                return $this->redirectToRoute('users');
            }
            if( $request->request->has('renewKey') ){
                $this->addFlash( 'key' , $userManager->renewKey( $user , $current->getUserIdentifier() ) );
                $this->addFlash( 'warning' , 'Neuer Schlüssel erzeugt – der alte gilt nicht mehr. Den neuen jetzt beim Aufrufer eintragen.' );
                return $this->redirectToRoute('user_edit' , [ 'id' => $id ] );
            }
            $userManager->update( $user , $request->request->getString('username') , $request->request->getString('kind') , $request->request->getString('password') , $current );
            $this->addFlash( 'success' , 'Nutzer gespeichert.' );
        } catch( \InvalidArgumentException $exception ){
            $this->addFlash( 'error' , $exception->getMessage() );
        }
        return $this->redirectToRoute('user_edit' , [ 'id' => $id ] );
    }

}
