<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\User;
use App\Repository\ConversationRepository;
use App\Repository\MessageRepository;
use App\Repository\UserRepository;
use App\Security\Voter\ConversationVoter;
use App\Service\ConversationService;
use App\Service\MercurePublisher;
use App\Service\PresenceRedisService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\Authorization;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

class ConversationController extends AbstractController
{
    public function __construct(
        private readonly Authorization $mercureAuthorization,
        private readonly MercurePublisher $mercurePublisher,
        private readonly PresenceRedisService $presenceRedis,
        #[Autowire(service: 'limiter.mark_read')]
        private readonly RateLimiterFactory $markReadLimiter,
        #[Autowire(service: 'limiter.typing')]
        private readonly RateLimiterFactory $typingLimiter,
    ) {}

    #[Route('/conversations', name: 'conversation_list', methods: ['GET'])]
    public function list(ConversationRepository $convRepo, MessageRepository $messageRepo): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $conversations = $convRepo->findForUser($user);

        $presenceMap = [];
        foreach ($conversations as $conv) {
            $otherId               = $conv->getOtherParticipant($user)->getId()->toRfc4122();
            $presenceMap[$otherId] = $this->presenceRedis->getPresence($otherId)->value;
        }

        $unreadMap = $messageRepo->countUnreadPerConversation($conversations, $user);

        return $this->render('conversation/list.html.twig', [
            'conversations' => $conversations,
            'presenceMap'   => $presenceMap,
            'unreadMap'     => $unreadMap,
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

    #[Route('/conversations/{id}/read', name: 'conversation_mark_read', methods: ['PATCH'])]
    public function markAsRead(string $id, ConversationRepository $convRepo, MessageRepository $messageRepo): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $limiter = $this->markReadLimiter->create($user->getId()->toRfc4122());
        if (!$limiter->consume()->isAccepted()) {
            return $this->json(['error' => 'Too many requests. Please slow down.'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        try {
            $conversation = $convRepo->find(Uuid::fromString($id));
        } catch (\Throwable) {
            return $this->json(['error' => 'Conversation not found.'], Response::HTTP_NOT_FOUND);
        }

        if (!$conversation instanceof Conversation) {
            return $this->json(['error' => 'Conversation not found.'], Response::HTTP_NOT_FOUND);
        }

        $this->denyAccessUnlessGranted(ConversationVoter::VIEW, $conversation);

        $count = $messageRepo->markAllAsReadBy($conversation, $user);

        // MercurePublisher::publishReadStatus() will be wired here in Phase 3

        return $this->json(['markedCount' => $count]);
    }

    #[Route('/conversations/{id}/messages', name: 'conversation_messages', methods: ['GET'])]
    public function messages(string $id, Request $request, ConversationRepository $convRepo, MessageRepository $messageRepo): JsonResponse
    {
        try {
            $conversation = $convRepo->find(Uuid::fromString($id));
        } catch (\Throwable) {
            return $this->json(['error' => 'Conversation not found.'], Response::HTTP_NOT_FOUND);
        }

        if (!$conversation instanceof Conversation) {
            return $this->json(['error' => 'Conversation not found.'], Response::HTTP_NOT_FOUND);
        }

        $this->denyAccessUnlessGranted(ConversationVoter::VIEW, $conversation);

        $beforeParam = $request->query->getString('before');

        if ($beforeParam !== '') {
            try {
                $beforeId = Uuid::fromString($beforeParam);
            } catch (\Throwable) {
                return $this->json(['error' => 'Invalid cursor.'], Response::HTTP_BAD_REQUEST);
            }
            $messages = $messageRepo->findBeforeId($conversation, $beforeId);
        } else {
            $messages = $messageRepo->findByConversation($conversation);
        }

        return $this->json(array_map(
            static fn(Message $m) => [
                'id'        => $m->getId()->toRfc4122(),
                'content'   => $m->getContent(),
                'createdAt' => $m->getCreatedAt()->format(\DateTimeInterface::ATOM),
                'sender'    => $m->getSender()->getUsername(),
            ],
            $messages,
        ));
    }

    #[Route('/conversations/{id}/typing', name: 'conversation_typing', methods: ['POST'])]
    public function typing(string $id, Request $request, ConversationRepository $convRepo): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $limiter = $this->typingLimiter->create($user->getId()->toRfc4122());
        if (!$limiter->consume()->isAccepted()) {
            return $this->json(null, Response::HTTP_TOO_MANY_REQUESTS);
        }

        if (!$this->isCsrfTokenValid('typing', $request->request->getString('_token'))) {
            return $this->json(['error' => 'Invalid CSRF token.'], Response::HTTP_FORBIDDEN);
        }

        try {
            $conversation = $convRepo->find(Uuid::fromString($id));
        } catch (\Throwable) {
            return $this->json(['error' => 'Conversation not found.'], Response::HTTP_NOT_FOUND);
        }

        if (!$conversation instanceof Conversation) {
            return $this->json(['error' => 'Conversation not found.'], Response::HTTP_NOT_FOUND);
        }

        $this->denyAccessUnlessGranted(ConversationVoter::VIEW, $conversation);

        $this->mercurePublisher->publishTyping($user, $conversation);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/conversations/{id}', name: 'conversation_show', methods: ['GET'])]
    public function show(string $id, Request $request, ConversationRepository $convRepo, MessageRepository $messageRepo): Response
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

        $convId  = $conversation->getId()->toRfc4122();
        $other   = $conversation->getOtherParticipant($user);
        $otherId = $other->getId()->toRfc4122();

        $this->mercureAuthorization->setCookie($request, [
            'conversation/' . $convId,
            'typing/'        . $convId,
            'presence/'      . $otherId,
        ]);

        $messages = $messageRepo->findByConversation($conversation);

        return $this->render('conversation/show.html.twig', [
            'conversation'   => $conversation,
            'messages'       => $messages,
            'other'          => $other,
            'otherPresence'  => $this->presenceRedis->getPresence($otherId)->value,
        ]);
    }
}
