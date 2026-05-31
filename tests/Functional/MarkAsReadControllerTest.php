<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Message;
use App\Repository\UserRepository;
use App\Service\ConversationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class MarkAsReadControllerTest extends WebTestCase
{
    public function testMarkAsReadRedirectsUnauthenticated(): void
    {
        $client = static::createClient();
        $client->request('PATCH', '/conversations/00000000-0000-7000-8000-000000000000/read');
        $this->assertResponseRedirects('/login');
    }

    public function testMarkAsReadReturns404ForNonExistentConversation(): void
    {
        $client = static::createClient();
        $alice = static::getContainer()->get(UserRepository::class)->findByUsername('alice');
        $client->loginUser($alice);

        $client->request('PATCH', '/conversations/00000000-0000-7000-8000-000000000000/read');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testMarkAsReadDeniesNonParticipant(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $userRepo = $container->get(UserRepository::class);

        $alice = $userRepo->findByUsername('alice');
        $bob = $userRepo->findByUsername('bob');
        $charlie = $userRepo->findByUsername('charlie');

        $conversation = $container->get(ConversationService::class)->findOrCreate($alice, $bob);

        $client->loginUser($charlie);
        $client->request('PATCH', '/conversations/'.$conversation->getId()->toRfc4122().'/read');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testMarkAsReadMarksUnreadMessagesAndReturnsCount(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $userRepo = $container->get(UserRepository::class);
        $em = $container->get(EntityManagerInterface::class);

        $alice = $userRepo->findByUsername('alice');
        $bob = $userRepo->findByUsername('bob');
        $conversation = $container->get(ConversationService::class)->findOrCreate($alice, $bob);

        $em->persist(new Message($conversation, $bob, 'Unread 1'));
        $em->persist(new Message($conversation, $bob, 'Unread 2'));
        $em->flush();

        $client->loginUser($alice);
        $client->request('PATCH', '/conversations/'.$conversation->getId()->toRfc4122().'/read');

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('markedCount', $data);
        $this->assertGreaterThanOrEqual(2, $data['markedCount']);
    }

    public function testMarkAsReadReturnsZeroWhenNothingUnread(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $userRepo = $container->get(UserRepository::class);

        $alice = $userRepo->findByUsername('alice');
        $bob = $userRepo->findByUsername('bob');
        $conversation = $container->get(ConversationService::class)->findOrCreate($alice, $bob);

        // Mark everything read first
        $container->get(\App\Repository\MessageRepository::class)->markAllAsReadBy($conversation, $alice);

        $client->loginUser($alice);
        $client->request('PATCH', '/conversations/'.$conversation->getId()->toRfc4122().'/read');

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame(0, $data['markedCount']);
    }
}
