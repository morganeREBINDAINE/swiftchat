<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Conversation;
use App\Entity\User;
use App\Repository\ConversationRepository;
use App\Repository\MessageRepository;
use App\Repository\UserRepository;
use App\Security\Voter\ConversationVoter;
use App\Service\ConversationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

class ConversationController extends AbstractController
{
    #[Route('/conversations', name: 'conversation_list', methods: ['GET'])]
    public function list(ConversationRepository $convRepo): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $conversations = $convRepo->findForUser($user);

        return $this->render('conversation/list.html.twig', [
            'conversations' => $conversations,
        ]);
    }

    #[Route('/conversations/new', name: 'conversation_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        UserRepository $userRepo,
        ConversationService $convService,
    ): Response {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('new_conversation', $request->request->getString('_token'))) {
                $this->addFlash('error', 'Invalid CSRF token.');

                return $this->redirectToRoute('conversation_new');
            }

            $username = trim($request->request->getString('username'));

            if ($username === '') {
                $this->addFlash('error', 'Please enter a username.');

                return $this->redirectToRoute('conversation_new');
            }

            if ($username === $currentUser->getUsername()) {
                $this->addFlash('error', 'You cannot start a conversation with yourself.');

                return $this->redirectToRoute('conversation_new');
            }

            $other = $userRepo->findByUsername($username);

            if ($other === null) {
                $this->addFlash('error', sprintf('No user found with username "%s".', $username));

                return $this->redirectToRoute('conversation_new');
            }

            $conversation = $convService->findOrCreate($currentUser, $other);

            return $this->redirectToRoute('conversation_show', [
                'id' => $conversation->getId()->toRfc4122(),
            ]);
        }

        return $this->render('conversation/new.html.twig');
    }

    #[Route('/conversations/{id}', name: 'conversation_show', methods: ['GET'])]
    public function show(string $id, ConversationRepository $convRepo, MessageRepository $messageRepo): Response
    {
        try {
            $conversation = $convRepo->find(Uuid::fromString($id));
        } catch (\Throwable) {
            throw $this->createNotFoundException('Conversation not found.');
        }

        if (!$conversation instanceof Conversation) {
            throw $this->createNotFoundException('Conversation not found.');
        }

        $this->denyAccessUnlessGranted(ConversationVoter::VIEW, $conversation);

        /** @var User $user */
        $user = $this->getUser();

        $messages = $messageRepo->findByConversation($conversation);

        return $this->render('conversation/show.html.twig', [
            'conversation' => $conversation,
            'messages' => $messages,
            'other' => $conversation->getOtherParticipant($user),
        ]);
    }
}
