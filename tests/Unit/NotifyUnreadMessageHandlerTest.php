<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\User;
use App\Messenger\Handler\NotifyUnreadMessageHandler;
use App\Messenger\Message\NotifyUnreadMessageMessage;
use App\Repository\MessageRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Uid\Uuid;

class NotifyUnreadMessageHandlerTest extends TestCase
{
    private MessageRepository&Stub $repository;
    private MailerInterface&MockObject $mailer;
    private LoggerInterface&MockObject $logger;
    private NotifyUnreadMessageHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createStub(MessageRepository::class);
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new NotifyUnreadMessageHandler(
            $this->repository,
            $this->mailer,
            $this->logger,
        );
    }

    public function testMessageNotFoundLogsWarningAndReturns(): void
    {
        $this->repository->method('find')->willReturn(null);

        $this->logger->expects($this->once())->method('warning');
        $this->logger->expects($this->never())->method('info');
        $this->mailer->expects($this->never())->method('send');

        ($this->handler)(new NotifyUnreadMessageMessage(Uuid::v7()->toRfc4122(), Uuid::v7()->toRfc4122()));
    }

    public function testAlreadyReadMessageSkipsSend(): void
    {
        $message = $this->createStub(Message::class);
        $message->method('isRead')->willReturn(true);

        $this->repository->method('find')->willReturn($message);

        $this->mailer->expects($this->never())->method('send');
        $this->logger->expects($this->never())->method('warning');
        $this->logger->expects($this->never())->method('info');

        ($this->handler)(new NotifyUnreadMessageMessage(Uuid::v7()->toRfc4122(), Uuid::v7()->toRfc4122()));
    }

    public function testEmailNotificationsDisabledSkipsSend(): void
    {
        $recipient = $this->createStub(User::class);
        $recipient->method('isEmailNotificationsEnabled')->willReturn(false);

        $this->repository->method('find')->willReturn($this->buildUnreadMessage($recipient));

        $this->mailer->expects($this->never())->method('send');
        $this->logger->expects($this->never())->method('info');
        $this->logger->expects($this->never())->method('warning');

        ($this->handler)(new NotifyUnreadMessageMessage(Uuid::v7()->toRfc4122(), Uuid::v7()->toRfc4122()));
    }

    public function testUnverifiedRecipientSkipsSend(): void
    {
        $recipient = $this->createStub(User::class);
        $recipient->method('isEmailNotificationsEnabled')->willReturn(true);
        $recipient->method('isVerified')->willReturn(false);

        $this->repository->method('find')->willReturn($this->buildUnreadMessage($recipient));

        $this->mailer->expects($this->never())->method('send');
        $this->logger->expects($this->never())->method('info');
        $this->logger->expects($this->never())->method('warning');

        ($this->handler)(new NotifyUnreadMessageMessage(Uuid::v7()->toRfc4122(), Uuid::v7()->toRfc4122()));
    }

    public function testSendsEmailAndLogsForEligibleRecipient(): void
    {
        $recipient = $this->createStub(User::class);
        $recipient->method('isEmailNotificationsEnabled')->willReturn(true);
        $recipient->method('isVerified')->willReturn(true);
        $recipient->method('getEmail')->willReturn('recipient@example.com');
        $recipient->method('getUsername')->willReturn('bob');
        $recipient->method('getId')->willReturn(Uuid::v7());

        $this->repository->method('find')->willReturn($this->buildUnreadMessage($recipient));

        $this->mailer->expects($this->once())->method('send');
        $this->logger->expects($this->once())->method('info');
        $this->logger->expects($this->never())->method('warning');

        ($this->handler)(new NotifyUnreadMessageMessage(Uuid::v7()->toRfc4122(), $recipient->getId()->toRfc4122()));
    }

    private function buildUnreadMessage(User&Stub $recipient): Message&Stub
    {
        $sender = $this->createStub(User::class);
        $sender->method('getUsername')->willReturn('alice');

        $conversation = $this->createStub(Conversation::class);
        $conversation->method('getOtherParticipant')->willReturn($recipient);

        $message = $this->createStub(Message::class);
        $message->method('isRead')->willReturn(false);
        $message->method('getSender')->willReturn($sender);
        $message->method('getConversation')->willReturn($conversation);
        $message->method('getContent')->willReturn('Hello!');
        $message->method('getCreatedAt')->willReturn(new \DateTimeImmutable());

        return $message;
    }
}
