<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\User;
use App\Service\MercurePublisher;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Uid\Uuid;

class MercurePublisherTest extends TestCase
{
    private HubInterface&MockObject $hub;
    private MercurePublisher $publisher;

    protected function setUp(): void
    {
        $this->hub       = $this->createMock(HubInterface::class);
        $this->publisher = new MercurePublisher($this->hub);
    }

    public function testPublishMessageSendsUpdateToConversationTopic(): void
    {
        [$alice, $bob, $conversation, $message] = $this->makeFixtures();

        $capturedUpdate = null;
        $this->hub
            ->expects($this->once())
            ->method('publish')
            ->with($this->isInstanceOf(Update::class))
            ->willReturnCallback(function (Update $update) use (&$capturedUpdate): string {
                $capturedUpdate = $update;
                return 'urn:uuid:test';
            });

        $this->publisher->publishMessage($message);

        $topics = $capturedUpdate->getTopics();
        $this->assertContains('conversation/' . $conversation->getId()->toRfc4122(), $topics);
    }

    public function testPublishMessagePayloadContainsRequiredFields(): void
    {
        [$alice, $bob, $conversation, $message] = $this->makeFixtures();

        $capturedUpdate = null;
        $this->hub
            ->expects($this->once())
            ->method('publish')
            ->willReturnCallback(function (Update $update) use (&$capturedUpdate): string {
                $capturedUpdate = $update;
                return 'urn:uuid:test';
            });

        $this->publisher->publishMessage($message);

        $payload = json_decode($capturedUpdate->getData(), true);

        $this->assertSame('new_message', $payload['type']);
        $this->assertSame($message->getId()->toRfc4122(), $payload['id']);
        $this->assertSame($message->getContent(), $payload['content']);
        $this->assertSame($alice->getId()->toRfc4122(), $payload['senderId']);
        $this->assertSame($alice->getUsername(), $payload['senderUsername']);
        $this->assertSame($conversation->getId()->toRfc4122(), $payload['conversationId']);
        $this->assertArrayHasKey('createdAt', $payload);
    }

    public function testPublishMessageIsPrivate(): void
    {
        [, , , $message] = $this->makeFixtures();

        $capturedUpdate = null;
        $this->hub
            ->expects($this->once())
            ->method('publish')
            ->willReturnCallback(function (Update $update) use (&$capturedUpdate): string {
                $capturedUpdate = $update;
                return 'urn:uuid:test';
            });

        $this->publisher->publishMessage($message);

        $this->assertTrue($capturedUpdate->isPrivate());
    }

    public function testPublishTypingSendsUpdateToTypingTopic(): void
    {
        [$alice, $bob, $conversation] = $this->makeFixtures();

        $capturedUpdate = null;
        $this->hub
            ->expects($this->once())
            ->method('publish')
            ->willReturnCallback(function (Update $update) use (&$capturedUpdate): string {
                $capturedUpdate = $update;
                return 'urn:uuid:test';
            });

        $this->publisher->publishTyping($alice, $conversation);

        $this->assertContains('typing/' . $conversation->getId()->toRfc4122(), $capturedUpdate->getTopics());
    }

    public function testPublishTypingPayload(): void
    {
        [$alice, $bob, $conversation] = $this->makeFixtures();

        $capturedUpdate = null;
        $this->hub
            ->expects($this->once())
            ->method('publish')
            ->willReturnCallback(function (Update $update) use (&$capturedUpdate): string {
                $capturedUpdate = $update;
                return 'urn:uuid:test';
            });

        $this->publisher->publishTyping($alice, $conversation);

        $payload = json_decode($capturedUpdate->getData(), true);

        $this->assertSame('typing', $payload['type']);
        $this->assertSame('alice', $payload['senderUsername']);
    }

    public function testPublishTypingIsPrivate(): void
    {
        [$alice, $bob, $conversation] = $this->makeFixtures();

        $capturedUpdate = null;
        $this->hub
            ->expects($this->once())
            ->method('publish')
            ->willReturnCallback(function (Update $update) use (&$capturedUpdate): string {
                $capturedUpdate = $update;
                return 'urn:uuid:test';
            });

        $this->publisher->publishTyping($alice, $conversation);

        $this->assertTrue($capturedUpdate->isPrivate());
    }

    public function testPublishPresenceSendsUpdateToPresenceTopic(): void
    {
        $userId = 'a1b2c3d4-0000-7000-0000-000000000001';

        $capturedUpdate = null;
        $this->hub
            ->expects($this->once())
            ->method('publish')
            ->willReturnCallback(function (Update $update) use (&$capturedUpdate): string {
                $capturedUpdate = $update;
                return 'urn:uuid:test';
            });

        $this->publisher->publishPresence($userId, \App\Enum\PresenceStatus::Online);

        $this->assertContains('presence/' . $userId, $capturedUpdate->getTopics());
    }

    public function testPublishPresencePayload(): void
    {
        $userId = 'a1b2c3d4-0000-7000-0000-000000000001';

        $capturedUpdate = null;
        $this->hub
            ->expects($this->once())
            ->method('publish')
            ->willReturnCallback(function (Update $update) use (&$capturedUpdate): string {
                $capturedUpdate = $update;
                return 'urn:uuid:test';
            });

        $this->publisher->publishPresence($userId, \App\Enum\PresenceStatus::Away);

        $payload = json_decode($capturedUpdate->getData(), true);

        $this->assertSame('presence', $payload['type']);
        $this->assertSame($userId, $payload['userId']);
        $this->assertSame('away', $payload['status']);
    }

    public function testPublishPresenceIsPrivate(): void
    {
        $userId = 'a1b2c3d4-0000-7000-0000-000000000001';

        $capturedUpdate = null;
        $this->hub
            ->expects($this->once())
            ->method('publish')
            ->willReturnCallback(function (Update $update) use (&$capturedUpdate): string {
                $capturedUpdate = $update;
                return 'urn:uuid:test';
            });

        $this->publisher->publishPresence($userId, \App\Enum\PresenceStatus::Offline);

        $this->assertTrue($capturedUpdate->isPrivate());
    }

    /** @return array{User, User, Conversation, Message} */
    private function makeFixtures(): array
    {
        $alice = new User();
        $alice->setUsername('alice');
        $alice->setEmail('alice@example.com');
        $alice->setPassword('hashed');

        $bob = new User();
        $bob->setUsername('bob');
        $bob->setEmail('bob@example.com');
        $bob->setPassword('hashed');

        $conversation = new Conversation($alice, $bob);
        $message      = new Message($conversation, $alice, 'Hello Bob!');

        return [$alice, $bob, $conversation, $message];
    }
}
