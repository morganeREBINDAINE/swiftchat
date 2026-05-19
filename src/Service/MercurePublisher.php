<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\User;
use App\Enum\PresenceStatus;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

class MercurePublisher
{
    public function __construct(private readonly HubInterface $hub)
    {
    }

    public function publishMessage(Message $message): void
    {
        $conversation = $message->getConversation();
        $topic = sprintf('conversation/%s', $conversation->getId()->toRfc4122());

        $update = new Update(
            topics: $topic,
            data: json_encode([
                'type' => 'new_message',
                'id' => $message->getId()->toRfc4122(),
                'content' => $message->getContent(),
                'createdAt' => $message->getCreatedAt()->format(\DateTimeInterface::ATOM),
                'senderId' => $message->getSender()->getId()->toRfc4122(),
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
                'type' => 'typing',
                'senderUsername' => $user->getUsername(),
            ], JSON_THROW_ON_ERROR),
            private: true,
        );

        $this->hub->publish($update);
    }

    public function publishReadStatus(Conversation $conversation, User $reader): void
    {
        $topic = sprintf('conversation/%s', $conversation->getId()->toRfc4122());

        $update = new Update(
            topics: $topic,
            data: json_encode([
                'type' => 'messages_read',
                'conversationId' => $conversation->getId()->toRfc4122(),
                'readerUsername' => $reader->getUsername(),
            ], JSON_THROW_ON_ERROR),
            private: true,
        );

        $this->hub->publish($update);
    }

    public function publishPresence(string $userId, PresenceStatus $status): void
    {
        $topic = sprintf('presence/%s', $userId);

        $update = new Update(
            topics: $topic,
            data: json_encode([
                'type' => 'presence',
                'userId' => $userId,
                'status' => $status->value,
            ], JSON_THROW_ON_ERROR),
            private: true,
        );

        $this->hub->publish($update);
    }
}
