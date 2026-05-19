<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Conversation;
use App\Repository\UserRepository;
use App\Service\ConversationService;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class MessageControllerTest extends WebTestCase
{
    /**
     * GET the conversation show page as $user, return the CSRF token and conversation ID.
     * This mirrors what a real browser does before POSTing a message.
     *
     * @return array{Conversation, string}
     */
    private function openConversation(KernelBrowser $client, string $asUsername, string $otherUsername): array
    {
        $userRepo = static::getContainer()->get(UserRepository::class);
        $user = $userRepo->findByUsername($asUsername);
        $other = $userRepo->findByUsername($otherUsername);

        $conversation = static::getContainer()
            ->get(ConversationService::class)
            ->findOrCreate($user, $other);

        $client->loginUser($user);
        $crawler = $client->request('GET', '/conversations/'.$conversation->getId()->toRfc4122());
        $token = $crawler->filter('input[name="_token"]')->attr('value');

        return [$conversation, $token];
    }

    public function testSendRedirectsUnauthenticated(): void
    {
        $client = static::createClient();
        $client->request('POST', '/conversations/00000000-0000-7000-8000-000000000000/messages');
        $this->assertResponseRedirects('/login');
    }

    public function testSendRejectsInvalidCsrfToken(): void
    {
        $client = static::createClient();
        $alice = static::getContainer()->get(UserRepository::class)->findByUsername('alice');
        $client->loginUser($alice);

        $client->request('POST', '/conversations/00000000-0000-7000-8000-000000000000/messages', [
            '_token' => 'bad-token',
            'content' => 'Hello',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Invalid CSRF token.', $data['error']);
    }

    public function testSendReturns404ForInvalidUuidString(): void
    {
        $client = static::createClient();
        [$conversation, $token] = $this->openConversation($client, 'alice', 'bob');

        $client->request('POST', '/conversations/not-a-valid-uuid/messages', [
            '_token' => $token,
            'content' => 'Hello',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Conversation not found.', $data['error']);
    }

    public function testSendReturns404ForNonExistentConversation(): void
    {
        $client = static::createClient();
        [$conversation, $token] = $this->openConversation($client, 'alice', 'bob');

        $client->request('POST', '/conversations/00000000-0000-7000-8000-000000000000/messages', [
            '_token' => $token,
            'content' => 'Hello',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Conversation not found.', $data['error']);
    }

    public function testSendDenies403ToNonParticipant(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $userRepo = $container->get(UserRepository::class);

        $alice = $userRepo->findByUsername('alice');
        $bob = $userRepo->findByUsername('bob');
        $charlie = $userRepo->findByUsername('charlie');

        $aliceBob = $container->get(ConversationService::class)->findOrCreate($alice, $bob);
        $charlieAlice = $container->get(ConversationService::class)->findOrCreate($charlie, $alice);

        // Charlie gets a valid send_message CSRF token from their own conversation show page
        $client->loginUser($charlie);
        $crawler = $client->request('GET', '/conversations/'.$charlieAlice->getId()->toRfc4122());
        $token = $crawler->filter('input[name="_token"]')->attr('value');

        // Charlie tries to post into alice–bob's conversation
        $client->request('POST', '/conversations/'.$aliceBob->getId()->toRfc4122().'/messages', [
            '_token' => $token,
            'content' => 'Hello',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testSendRejectsBlankContent(): void
    {
        $client = static::createClient();
        [$conversation, $token] = $this->openConversation($client, 'alice', 'bob');

        $client->request('POST', '/conversations/'.$conversation->getId()->toRfc4122().'/messages', [
            '_token' => $token,
            'content' => '   ',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Message content cannot be empty.', $data['error']);
    }

    public function testSendRejectsContentTooLong(): void
    {
        $client = static::createClient();
        [$conversation, $token] = $this->openConversation($client, 'alice', 'bob');

        $client->request('POST', '/conversations/'.$conversation->getId()->toRfc4122().'/messages', [
            '_token' => $token,
            'content' => str_repeat('a', 5001),
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Message is too long (max 5000 characters).', $data['error']);
    }

    public function testSendCreatesMessageAndReturns201(): void
    {
        $client = static::createClient();
        [$conversation, $token] = $this->openConversation($client, 'alice', 'bob');

        $client->request('POST', '/conversations/'.$conversation->getId()->toRfc4122().'/messages', [
            '_token' => $token,
            'content' => 'Hello, Bob!',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $data);
        $this->assertSame('Hello, Bob!', $data['content']);
        $this->assertSame('alice', $data['sender']);
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/',
            $data['createdAt'],
        );
    }

    public function testSendContentIsStoredAsPlainText(): void
    {
        $client = static::createClient();
        [$conversation, $token] = $this->openConversation($client, 'alice', 'bob');

        $client->request('POST', '/conversations/'.$conversation->getId()->toRfc4122().'/messages', [
            '_token' => $token,
            'content' => '<script>alert(1)</script>',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('<script>alert(1)</script>', $data['content']);
    }
}
