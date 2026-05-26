<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\User;
use App\Messenger\Message\NotifyUnreadMessageMessage;
use App\Repository\ConversationRepository;
use App\Security\Voter\ConversationVoter;
use App\Service\MercurePublisher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

class MessageController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MercurePublisher $mercurePublisher,
        private readonly MessageBusInterface $bus,
        #[Autowire(service: 'limiter.send_message')]
        private readonly RateLimiterFactory $sendMessageLimiter,
    ) {
    }

    #[Route('/conversations/{id}/messages', name: 'message_send', methods: ['POST'])]
    public function send(string $id, Request $request, ConversationRepository $convRepo): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $limiter = $this->sendMessageLimiter->create($user->getId()->toRfc4122());
        if (!$limiter->consume()->isAccepted()) {
            return $this->json(
                ['error' => 'Too many messages. Please slow down.'],
                Response::HTTP_TOO_MANY_REQUESTS,
            );
        }

        if (!$this->isCsrfTokenValid('send_message', $request->request->getString('_token'))) {
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

        $this->denyAccessUnlessGranted(ConversationVoter::SEND, $conversation);

        $content = trim($request->request->getString('content'));

        if ('' === $content) {
            return $this->json(['error' => 'Message content cannot be empty.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (mb_strlen($content) > 5000) {
            return $this->json(['error' => 'Message is too long (max 5000 characters).'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $message = new Message($conversation, $user, $content);
        $conversation->touch();

        $this->em->persist($message);
        $this->em->flush();

        $this->mercurePublisher->publishMessage($message);

        $recipient = $conversation->getOtherParticipant($user);
        $this->bus->dispatch(
            new NotifyUnreadMessageMessage(
                $message->getId()->toRfc4122(),
                $recipient->getId()->toRfc4122(),
            ),
            [new DelayStamp(300_000)],
        );

        return $this->json([
            'id' => $message->getId()->toRfc4122(),
            'content' => $message->getContent(),
            'createdAt' => $message->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'sender' => $user->getUsername(),
        ], Response::HTTP_CREATED);
    }
}
