<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\User;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

class MercurePublisher
{
    public function __construct(private readonly HubInterface $hub) {}

    public function publishMessage(Message $message): void
    {
        $conversation = $message->getConversation();
        $topic = sprintf('conversation/%s', $conversation->getId()->toRfc4122());

        $update = new Update(
            topics: $topic,
            data: json_encode([
                'type'           => 'new_message',
                'id'             => $message->getId()->toRfc4122(),
                'content'        => $message->getContent(),
                'createdAt'      => $message->getCreatedAt()->format(\DateTimeInterface::ATOM),
                'senderId'       => $message->getSender()->getId()->toRfc4122(),
                'senderUsername' => $message->getSender()->getUsername(),
                'conversationId' => $conversation->getId()->toRfc4122(),
            ], JSON_THROW_ON_ERROR),
            private: true,
        );

        $this->hub->publish($update);
    }

    public function publishTyping(User $user, Conversation $conv): void
    {
        $topic = sprintf('typing/%s', $conv->getId()->toRfc4122());

        $update = new Update(
            topics: $topic,
            data: json_encode([
                'type'           => 'typing',
                'senderUsername' => $user->getUsername(),
            ], JSON_THROW_ON_ERROR),
            private: true,
        );

        $this->hub->publish($update);
    }

    public function publishPresence(User $user): void
    {
        $topic = sprintf('presence/%s', $user->getId()->toRfc4122());

        $update = new Update(
            topics: $topic,
            data: json_encode([
                'type'       => 'presence',
                'userId'     => $user->getId()->toRfc4122(),
                'status'     => $user->getPresenceStatus()->value,
                'lastSeenAt' => $user->getLastSeenAt()?->format(\DateTimeInterface::ATOM),
            ], JSON_THROW_ON_ERROR),
            private: true,
        );

        $this->hub->publish($update);
    }
}
