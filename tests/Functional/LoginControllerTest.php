<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class LoginControllerTest extends WebTestCase
{
    public function testShowsLoginForm(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('input[name="_username"]');
        $this->assertSelectorExists('input[name="_password"]');
        $this->assertSelectorExists('input[name="_remember_me"]');
    }

    public function testSuccessfulLogin(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/login');

        $client->submit($crawler->selectButton('Log in')->form([
            '_username' => 'alice@swiftchat.app',
            '_password' => 'Password1!',
        ]));

        $this->assertResponseRedirects('/profile');
    }

    public function testSuccessfulLoginWithRememberMe(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/login');

        $client->submit($crawler->selectButton('Log in')->form([
            '_username'     => 'alice@swiftchat.app',
            '_password'     => 'Password1!',
            '_remember_me'  => true,
        ]));

        $this->assertResponseRedirects('/profile');
        $this->assertNotNull($client->getCookieJar()->get('REMEMBERME'));
    }

    public function testWrongPasswordIsRejected(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/login');

        $client->submit($crawler->selectButton('Log in')->form([
            '_username' => 'alice@swiftchat.app',
            '_password' => 'wrong-password',
        ]));

        // form_login redirects back to /login on failure
        $this->assertResponseRedirects('/login');
        $client->followRedirect();
        $this->assertSelectorTextContains('.flash--error', 'Invalid credentials');
    }

    public function testUnknownEmailIsRejected(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/login');

        $client->submit($crawler->selectButton('Log in')->form([
            '_username' => 'nobody@example.com',
            '_password' => 'Password1!',
        ]));

        $this->assertResponseRedirects('/login');
        $client->followRedirect();
        $this->assertSelectorTextContains('.flash--error', 'Invalid credentials');
    }

    public function testUnverifiedAccountIsRejected(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/login');

        // charlie is in fixtures with isVerified = false
        $client->submit($crawler->selectButton('Log in')->form([
            '_username' => 'charlie@swiftchat.app',
            '_password' => 'Password1!',
        ]));

        $this->assertResponseRedirects('/login');
        $client->followRedirect();
        $this->assertSelectorTextContains('.flash--error', 'verify your email');
    }

    public function testAuthenticatedUserIsRedirectedAwayFromLogin(): void
    {
        $client = static::createClient();

        // Log in first
        $crawler = $client->request('GET', '/login');
        $client->submit($crawler->selectButton('Log in')->form([
            '_username' => 'alice@swiftchat.app',
            '_password' => 'Password1!',
        ]));
        $client->followRedirect();

        // Visiting /login again should redirect to /profile
        $client->request('GET', '/login');
        $this->assertResponseRedirects('/profile');
    }
}
