<?php

declare(strict_types=1);

namespace App\Messenger\Handler;

use App\Messenger\Message\NotifyUnreadMessageMessage;
use App\Repository\MessageRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final class NotifyUnreadMessageHandler
{
    public function __construct(
        private readonly MessageRepository $messageRepository,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(NotifyUnreadMessageMessage $notification): void
    {
        $message = $this->messageRepository->find(Uuid::fromString($notification->messageId));

        if (null === $message) {
            $this->logger->warning('NotifyUnreadMessage: message not found', [
                'messageId' => $notification->messageId,
            ]);

            return;
        }

        if ($message->isRead()) {
            return;
        }

        $recipient = $message->getConversation()->getOtherParticipant($message->getSender());

        if (!$recipient->isEmailNotificationsEnabled()) {
            return;
        }

        if (!$recipient->isVerified()) {
            return;
        }

        $email = (new TemplatedEmail())
            ->from(new Address('noreply@swiftchat.app', 'SwiftChat'))
            ->to($recipient->getEmail())
            ->subject('You have an unread message on SwiftChat')
            ->htmlTemplate('emails/unread_message_notification.html.twig')
            ->textTemplate('emails/unread_message_notification.txt.twig')
            ->context([
                'senderUsername' => $message->getSender()->getUsername(),
                'recipientUsername' => $recipient->getUsername(),
                'messageContent' => $message->getContent(),
                'sentAt' => $message->getCreatedAt(),
            ]);

        $this->mailer->send($email);

        $this->logger->info('Unread message notification sent', [
            'messageId' => $notification->messageId,
            'recipientId' => $recipient->getId()->toRfc4122(),
        ]);
    }
}
