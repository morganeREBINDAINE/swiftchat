<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Repository\UserRepository;
use App\Service\ConversationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class ConversationMessagesTest extends WebTestCase
{
    public function testRedirectsUnauthenticated(): void
    {
        $client = static::createClient();
        $client->request('GET', '/conversations/00000000-0000-7000-8000-000000000000/messages');
        $this->assertResponseRedirects('/login');
    }

    public function testReturns404ForNonExistentConversation(): void
    {
        $client = static::createClient();
        $alice = static::getContainer()->get(UserRepository::class)->findByUsername('alice');
        $client->loginUser($alice);

        $client->request('GET', '/conversations/00000000-0000-7000-8000-000000000000/messages');
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testDenies403ToNonParticipant(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $userRepo = $container->get(UserRepository::class);

        $alice = $userRepo->findByUsername('alice');
        $bob = $userRepo->findByUsername('bob');
        $charlie = $userRepo->findByUsername('charlie');

        $conversation = $container->get(ConversationService::class)->findOrCreate($alice, $bob);

        $client->loginUser($charlie);
        $client->request('GET', '/conversations/'.$conversation->getId()->toRfc4122().'/messages');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testReturnsEmptyArrayForNewConversation(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $userRepo = $container->get(UserRepository::class);

        $alice = $userRepo->findByUsername('alice');
        $bob = $userRepo->findByUsername('bob');
        $conversation = $container->get(ConversationService::class)->findOrCreate($alice, $bob);

        $client->loginUser($alice);
        $client->request('GET', '/conversations/'.$conversation->getId()->toRfc4122().'/messages');

        $this->assertResponseIsSuccessful();
        $this->assertSame([], json_decode($client->getResponse()->getContent(), true));
    }

    public function testReturnsMessagesInChronologicalOrder(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $userRepo = $container->get(UserRepository::class);

        $alice = $userRepo->findByUsername('alice');
        $bob = $userRepo->findByUsername('bob');
        $conversation = $container->get(ConversationService::class)->findOrCreate($alice, $bob);

        $this->seedMessages($conversation, $alice, ['first', 'second', 'third']);

        $client->loginUser($alice);
        $client->request('GET', '/conversations/'.$conversation->getId()->toRfc4122().'/messages');

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);

        $contents = array_column($data, 'content');
        $this->assertContains('first', $contents);
        $this->assertContains('second', $contents);
        $this->assertContains('third', $contents);
        $this->assertSame(['first', 'second', 'third'], array_slice($contents, -3));
    }

    public function testCursorReturnsOnlyOlderMessages(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $userRepo = $container->get(UserRepository::class);

        $alice = $userRepo->findByUsername('alice');
        $bob = $userRepo->findByUsername('bob');
        $conversation = $container->get(ConversationService::class)->findOrCreate($alice, $bob);

        $messages = $this->seedMessages($conversation, $alice, ['msg-1', 'msg-2', 'msg-3', 'msg-4', 'msg-5']);
        $cursor = $messages[2]->getId()->toRfc4122(); // before msg-3

        $client->loginUser($alice);
        $client->request('GET', '/conversations/'.$conversation->getId()->toRfc4122().'/messages?before='.$cursor);

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $contents = array_column($data, 'content');

        $this->assertContains('msg-1', $contents);
        $this->assertContains('msg-2', $contents);
        $this->assertNotContains('msg-3', $contents);
        $this->assertNotContains('msg-4', $contents);
        $this->assertNotContains('msg-5', $contents);
    }

    public function testCursorResultsAreInChronologicalOrder(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $userRepo = $container->get(UserRepository::class);

        $alice = $userRepo->findByUsername('alice');
        $bob = $userRepo->findByUsername('bob');
        $conversation = $container->get(ConversationService::class)->findOrCreate($alice, $bob);

        $messages = $this->seedMessages($conversation, $alice, ['a', 'b', 'c', 'd', 'e']);
        $cursor = $messages[4]->getId()->toRfc4122(); // before e → expect a, b, c, d

        $client->loginUser($alice);
        $client->request('GET', '/conversations/'.$conversation->getId()->toRfc4122().'/messages?before='.$cursor);

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertSame(['a', 'b', 'c', 'd'], array_column($data, 'content'));
    }

    public function testReturns400ForInvalidCursor(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $userRepo = $container->get(UserRepository::class);

        $alice = $userRepo->findByUsername('alice');
        $bob = $userRepo->findByUsername('bob');
        $conversation = $container->get(ConversationService::class)->findOrCreate($alice, $bob);

        $client->loginUser($alice);
        $client->request('GET', '/conversations/'.$conversation->getId()->toRfc4122().'/messages?before=not-a-uuid');

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Invalid cursor.', $data['error']);
    }

    public function testResponseShapeIsCorrect(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $userRepo = $container->get(UserRepository::class);

        $alice = $userRepo->findByUsername('alice');
        $bob = $userRepo->findByUsername('bob');
        $conversation = $container->get(ConversationService::class)->findOrCreate($alice, $bob);

        $this->seedMessages($conversation, $alice, ['hello']);

        $client->loginUser($alice);
        $client->request('GET', '/conversations/'.$conversation->getId()->toRfc4122().'/messages');

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $msg = end($data);

        $this->assertArrayHasKey('id', $msg);
        $this->assertArrayHasKey('content', $msg);
        $this->assertArrayHasKey('createdAt', $msg);
        $this->assertArrayHasKey('sender', $msg);
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/',
            $msg['createdAt'],
        );
    }

    /**
     * @param string[] $contents
     *
     * @return Message[]
     */
    private function seedMessages(Conversation $conversation, mixed $sender, array $contents): array
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $messages = [];

        foreach ($contents as $content) {
            $message = new Message($conversation, $sender, $content);
            $em->persist($message);
            $messages[] = $message;
        }

        $em->flush();

        return $messages;
    }
}
