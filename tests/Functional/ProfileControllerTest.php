<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

class ProfileControllerTest extends WebTestCase
{
    public function testProfilePageIsAccessible(): void
    {
        $client = static::createClient();
        $alice = static::getContainer()->get(UserRepository::class)->findByUsername('alice');
        $client->loginUser($alice);

        $client->request('GET', '/profile');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
    }

    public function testProfilePageRedirectsWhenUnauthenticated(): void
    {
        $client = static::createClient();
        $client->request('GET', '/profile');

        $this->assertResponseRedirects('/login');
    }

    public function testEditProfileUpdatesUsername(): void
    {
        $client = static::createClient();
        $alice = static::getContainer()->get(UserRepository::class)->findByUsername('alice');
        $client->loginUser($alice);

        $crawler = $client->request('GET', '/profile');
        $client->submit($crawler->filter('form[action$="/profile/edit"]')->form([
            'edit_profile_form[username]' => 'alice_updated',
            'edit_profile_form[email]' => 'alice@swiftchat.app',
            'edit_profile_form[emailNotificationsEnabled]' => true,
        ]));

        $this->assertResponseRedirects('/profile');
        $client->followRedirect();
        $this->assertSelectorTextContains('.flash--success', 'Profile updated');
    }

    public function testEditProfileRejectsShortUsername(): void
    {
        $client = static::createClient();
        $alice = static::getContainer()->get(UserRepository::class)->findByUsername('alice');
        $client->loginUser($alice);

        $crawler = $client->request('GET', '/profile');
        $client->submit($crawler->filter('form[action$="/profile/edit"]')->form([
            'edit_profile_form[username]' => 'ab',
            'edit_profile_form[email]' => 'alice@swiftchat.app',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertSelectorExists('.form-errors');
    }

    public function testChangePasswordSucceeds(): void
    {
        $client = static::createClient();
        $alice = static::getContainer()->get(UserRepository::class)->findByUsername('alice');
        $client->loginUser($alice);

        $crawler = $client->request('GET', '/profile');
        $client->submit($crawler->filter('form[action$="/profile/password"]')->form([
            'change_password_form[currentPassword]' => 'Password1!',
            'change_password_form[newPassword][first]' => 'NewPassword1!',
            'change_password_form[newPassword][second]' => 'NewPassword1!',
        ]));

        $this->assertResponseRedirects('/profile');
        $client->followRedirect();
        $this->assertSelectorTextContains('.flash--success', 'Password changed');
    }

    public function testChangePasswordFailsWithWrongCurrentPassword(): void
    {
        $client = static::createClient();
        $alice = static::getContainer()->get(UserRepository::class)->findByUsername('alice');
        $client->loginUser($alice);

        $crawler = $client->request('GET', '/profile');
        $client->submit($crawler->filter('form[action$="/profile/password"]')->form([
            'change_password_form[currentPassword]' => 'wrong-password',
            'change_password_form[newPassword][first]' => 'NewPassword1!',
            'change_password_form[newPassword][second]' => 'NewPassword1!',
        ]));

        $this->assertResponseRedirects('/profile');
        $client->followRedirect();
        $this->assertSelectorTextContains('.flash--error', 'Current password is incorrect');
    }

    public function testAvatarUploadSucceedsAndDoesNotThrowSerializationError(): void
    {
        $client = static::createClient();
        $alice = static::getContainer()->get(UserRepository::class)->findByUsername('alice');
        $client->loginUser($alice);

        // Minimal 1×1 white PNG — smallest valid image
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwADhQGAWjR9awAAAABJRU5ErkJggg==');
        $path = tempnam(sys_get_temp_dir(), 'avatar_') . '.png';
        file_put_contents($path, $png);

        $file = new UploadedFile($path, 'avatar.png', 'image/png', null, true);

        $crawler = $client->request('GET', '/profile');
        $client->submit($crawler->filter('form[action$="/profile/avatar"]')->form([
            'avatar_upload_form[avatarFile][file]' => $file,
        ]));

        // If serialization fails the response would be a 500 — assert it redirects cleanly
        $this->assertResponseRedirects('/profile');
        $client->followRedirect();
        $this->assertSelectorTextContains('.flash--success', 'Avatar updated');

        $updated = static::getContainer()->get(UserRepository::class)->findByUsername('alice');
        $this->assertNotNull($updated->getAvatar());

        @unlink($path);
    }
}
