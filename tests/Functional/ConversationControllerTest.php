<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Repository\UserRepository;
use App\Service\ConversationService;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ConversationControllerTest extends WebTestCase
{
    // --- list ---

    public function testListRedirectsUnauthenticated(): void
    {
        $client = static::createClient();
        $client->request('GET', '/conversations');
        $this->assertResponseRedirects('/login');
    }

    public function testListIsAccessibleWhenAuthenticated(): void
    {
        $client = static::createClient();
        $alice = static::getContainer()->get(UserRepository::class)->findByUsername('alice');
        $client->loginUser($alice);

        $client->request('GET', '/conversations');
        $this->assertResponseIsSuccessful();
    }

    public function testNewShowsFormWhenAuthenticated(): void
    {
        $client = static::createClient();
        $alice = static::getContainer()->get(UserRepository::class)->findByUsername('alice');
        $client->loginUser($alice);

        $client->request('GET', '/conversations/new');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('input[name="username"]');
    }

    public function testNewPostRejectsInvalidCsrfToken(): void
    {
        $client = static::createClient();
        $alice = static::getContainer()->get(UserRepository::class)->findByUsername('alice');
        $client->loginUser($alice);

        $client->request('POST', '/conversations/new', [
            '_token'   => 'bad-token',
            'username' => 'bob',
        ]);

        $this->assertResponseRedirects('/conversations/new');
        $client->followRedirect();
        $this->assertSelectorTextContains('.flash--error', 'Invalid CSRF token');
    }

    public function testNewPostRejectsBlankUsername(): void
    {
        $client = static::createClient();
        $alice = static::getContainer()->get(UserRepository::class)->findByUsername('alice');
        $client->loginUser($alice);

        $crawler = $client->request('GET', '/conversations/new');
        $token = $crawler->filter('input[name="_token"]')->attr('value');

        $client->request('POST', '/conversations/new', [
            '_token'   => $token,
            'username' => '   ',
        ]);

        $this->assertResponseRedirects('/conversations/new');
        $client->followRedirect();
        $this->assertSelectorTextContains('.flash--error', 'Please enter a username');
    }

    public function testNewPostRejectsSelfConversation(): void
    {
        $client = static::createClient();
        $alice = static::getContainer()->get(UserRepository::class)->findByUsername('alice');
        $client->loginUser($alice);

        $crawler = $client->request('GET', '/conversations/new');
        $token = $crawler->filter('input[name="_token"]')->attr('value');

        $client->request('POST', '/conversations/new', [
            '_token'   => $token,
            'username' => 'alice',
        ]);

        $this->assertResponseRedirects('/conversations/new');
        $client->followRedirect();
        $this->assertSelectorTextContains('.flash--error', 'cannot start a conversation with yourself');
    }

    public function testNewPostRejectsUnknownUsername(): void
    {
        $client = static::createClient();
        $alice = static::getContainer()->get(UserRepository::class)->findByUsername('alice');
        $client->loginUser($alice);

        $crawler = $client->request('GET', '/conversations/new');
        $token = $crawler->filter('input[name="_token"]')->attr('value');

        $client->request('POST', '/conversations/new', [
            '_token'   => $token,
            'username' => 'nobody',
        ]);

        $this->assertResponseRedirects('/conversations/new');
        $client->followRedirect();
        $this->assertSelectorTextContains('.flash--error', 'No user found with username');
    }

    public function testNewPostCreatesConversationAndRedirects(): void
    {
        $client = static::createClient();
        $alice = static::getContainer()->get(UserRepository::class)->findByUsername('alice');
        $client->loginUser($alice);

        $crawler = $client->request('GET', '/conversations/new');
        $token = $crawler->filter('input[name="_token"]')->attr('value');

        $client->request('POST', '/conversations/new', [
            '_token'   => $token,
            'username' => 'bob',
        ]);

        $this->assertResponseRedirects();
        $this->assertMatchesRegularExpression(
            '#^/conversations/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$#',
            $client->getResponse()->headers->get('Location'),
        );
    }

    // --- show ---

    public function testShowRedirectsUnauthenticated(): void
    {
        $client = static::createClient();
        $client->request('GET', '/conversations/00000000-0000-7000-8000-000000000000');
        $this->assertResponseRedirects('/login');
    }

    public function testShowReturns404ForInvalidUuidString(): void
    {
        $client = static::createClient();
        $alice = static::getContainer()->get(UserRepository::class)->findByUsername('alice');
        $client->loginUser($alice);

        $client->request('GET', '/conversations/not-a-valid-uuid');
        $this->assertResponseStatusCodeSame(404);
    }

    public function testShowReturns404ForNonExistentConversation(): void
    {
        $client = static::createClient();
        $alice = static::getContainer()->get(UserRepository::class)->findByUsername('alice');
        $client->loginUser($alice);

        $client->request('GET', '/conversations/00000000-0000-7000-8000-000000000000');
        $this->assertResponseStatusCodeSame(404);
    }

    public function testShowDeniesAccessToNonParticipant(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $userRepo = $container->get(UserRepository::class);

        $alice = $userRepo->findByUsername('alice');
        $bob = $userRepo->findByUsername('bob');
        $charlie = $userRepo->findByUsername('charlie');

        $conversation = $container->get(ConversationService::class)->findOrCreate($alice, $bob);

        $client->loginUser($charlie);
        $client->request('GET', '/conversations/' . $conversation->getId()->toRfc4122());

        $this->assertResponseStatusCodeSame(403);
    }

    public function testShowIsAccessibleToParticipant(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $userRepo = $container->get(UserRepository::class);

        $alice = $userRepo->findByUsername('alice');
        $bob = $userRepo->findByUsername('bob');

        $conversation = $container->get(ConversationService::class)->findOrCreate($alice, $bob);

        $client->loginUser($alice);
        $client->request('GET', '/conversations/' . $conversation->getId()->toRfc4122());

        $this->assertResponseIsSuccessful();
    }
}
