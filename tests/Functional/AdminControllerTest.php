<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class AdminControllerTest extends WebTestCase
{
    public function testRedirectsUnauthenticated(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin');
        $this->assertResponseRedirects('/login');
    }

    public function testDeniesNonAdminUser(): void
    {
        $client = static::createClient();
        $alice = static::getContainer()->get(UserRepository::class)->findByUsername('alice');
        $client->loginUser($alice);

        $client->request('GET', '/admin');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testDashboardIsAccessibleToAdmin(): void
    {
        $client = static::createClient();
        $admin = static::getContainer()->get(UserRepository::class)->findByUsername('admin');
        $client->loginUser($admin);

        $client->request('GET', '/admin');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('table');
    }

    public function testToggleEnabledRejectsInvalidCsrf(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $admin = $container->get(UserRepository::class)->findByUsername('admin');
        $bob = $container->get(UserRepository::class)->findByUsername('bob');
        $client->loginUser($admin);

        $client->request('POST', '/admin/users/'.$bob->getId()->toRfc4122().'/toggle-enabled', [
            '_token' => 'bad-token',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testToggleEnabledTogglesAccount(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $admin = $container->get(UserRepository::class)->findByUsername('admin');
        $bob = $container->get(UserRepository::class)->findByUsername('bob');
        $client->loginUser($admin);

        $bobId = $bob->getId()->toRfc4122();
        $wasEnabled = $bob->isEnabled();

        // Fetch the token from the admin page form
        $crawler = $client->request('GET', '/admin');
        $formSelector = 'form[action*="'.$bobId.'/toggle-enabled"]';
        $token = $crawler->filter($formSelector.' input[name="_token"]')->attr('value');

        $client->request('POST', '/admin/users/'.$bobId.'/toggle-enabled', [
            '_token' => $token,
        ]);

        $this->assertResponseRedirects('/admin');
        $client->followRedirect();

        $expectedWord = $wasEnabled ? 'disabled' : 'enabled';
        $this->assertSelectorTextContains('.flash--success', $expectedWord);
    }

    public function testAdminCannotDisableOwnAccount(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $admin = $container->get(UserRepository::class)->findByUsername('admin');
        $client->loginUser($admin);

        $adminId = $admin->getId()->toRfc4122();

        $crawler = $client->request('GET', '/admin');
        $formSelector = 'form[action*="'.$adminId.'/toggle-enabled"]';
        $token = $crawler->filter($formSelector.' input[name="_token"]')->attr('value');

        $client->request('POST', '/admin/users/'.$adminId.'/toggle-enabled', [
            '_token' => $token,
        ]);

        $this->assertResponseRedirects('/admin');
        $client->followRedirect();
        $this->assertSelectorTextContains('.flash--error', 'cannot disable your own account');
    }
}
