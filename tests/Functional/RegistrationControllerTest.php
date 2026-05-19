<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class RegistrationControllerTest extends WebTestCase
{
    public function testShowsRegistrationForm(): void
    {
        $client = static::createClient();
        $client->request('GET', '/register');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
        $this->assertSelectorExists('input[name="registration_form[email]"]');
    }

    public function testSuccessfulRegistration(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/register');

        $client->submit($crawler->selectButton('Create account')->form([
            'registration_form[username]' => 'newuser',
            'registration_form[email]' => 'newuser@example.com',
            'registration_form[plainPassword][first]' => 'StrongPass1!',
            'registration_form[plainPassword][second]' => 'StrongPass1!',
            'registration_form[agreeTerms]' => true,
        ]));

        $this->assertResponseRedirects('/login');
        $client->followRedirect();
        $this->assertSelectorTextContains('.flash--success', 'Check your inbox');

        $user = static::getContainer()->get(UserRepository::class)
            ->findOneBy(['email' => 'newuser@example.com']);

        $this->assertNotNull($user);
        $this->assertFalse($user->isVerified());
    }

    public function testDuplicateEmailIsRejected(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/register');

        $client->submit($crawler->selectButton('Create account')->form([
            'registration_form[username]' => 'uniqueuser',
            'registration_form[email]' => 'alice@swiftchat.app', // already exists in fixtures
            'registration_form[plainPassword][first]' => 'StrongPass1!',
            'registration_form[plainPassword][second]' => 'StrongPass1!',
            'registration_form[agreeTerms]' => true,
        ]));

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorTextContains('body', 'already an account');
    }

    public function testDuplicateUsernameIsRejected(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/register');

        $client->submit($crawler->selectButton('Create account')->form([
            'registration_form[username]' => 'alice', // already exists in fixtures
            'registration_form[email]' => 'unique@example.com',
            'registration_form[plainPassword][first]' => 'StrongPass1!',
            'registration_form[plainPassword][second]' => 'StrongPass1!',
            'registration_form[agreeTerms]' => true,
        ]));

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorTextContains('body', 'username is already taken');
    }

    public function testWeakPasswordIsRejected(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/register');

        $client->submit($crawler->selectButton('Create account')->form([
            'registration_form[username]' => 'weakuser',
            'registration_form[email]' => 'weakuser@example.com',
            'registration_form[plainPassword][first]' => 'short',
            'registration_form[plainPassword][second]' => 'short',
            'registration_form[agreeTerms]' => true,
        ]));

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorTextContains('body', 'too short');
    }

    public function testPasswordMismatchIsRejected(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/register');

        $client->submit($crawler->selectButton('Create account')->form([
            'registration_form[username]' => 'mismatchuser',
            'registration_form[email]' => 'mismatch@example.com',
            'registration_form[plainPassword][first]' => 'StrongPass1!',
            'registration_form[plainPassword][second]' => 'DifferentPass1!',
            'registration_form[agreeTerms]' => true,
        ]));

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorTextContains('body', 'values do not match');
    }
}
