<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelper;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

class EmailVerificationTest extends WebTestCase
{
    public function testSuccessfulVerification(): void
    {
        $client = static::createClient();

        $charlie = static::getContainer()->get(UserRepository::class)
            ->findOneBy(['email' => 'charlie@swiftchat.app']);

        $this->assertFalse($charlie->isVerified());

        $signedUrl = static::getContainer()->get(VerifyEmailHelperInterface::class)
            ->generateSignature(
                'app_verify_email',
                $charlie->getId()->toRfc4122(),
                $charlie->getEmail(),
                ['id' => $charlie->getId()->toRfc4122()],
            )
            ->getSignedUrl();

        $client->request('GET', $signedUrl);

        $this->assertResponseRedirects('/login');
        $client->followRedirect();
        $this->assertSelectorTextContains('.flash--success', 'verified');

        static::getContainer()->get(EntityManagerInterface::class)->clear();
        $charlie = static::getContainer()->get(UserRepository::class)
            ->findOneBy(['email' => 'charlie@swiftchat.app']);

        $this->assertTrue($charlie->isVerified());
    }

    public function testMissingIdParameterIsRejected(): void
    {
        $client = static::createClient();
        $client->request('GET', '/verify/email');

        $this->assertResponseRedirects('/login');
        $client->followRedirect();
        $this->assertSelectorTextContains('.flash--error', 'Invalid verification link');
    }

    public function testInvalidUserIdIsRejected(): void
    {
        $client = static::createClient();
        $client->request('GET', '/verify/email?id=not-a-valid-uuid');

        $this->assertResponseRedirects('/login');
        $client->followRedirect();
        $this->assertSelectorTextContains('.flash--error', 'Invalid verification link');
    }

    public function testTamperedSignatureIsRejected(): void
    {
        $client = static::createClient();

        $charlie = static::getContainer()->get(UserRepository::class)
            ->findOneBy(['email' => 'charlie@swiftchat.app']);

        $signedUrl = static::getContainer()->get(VerifyEmailHelperInterface::class)
            ->generateSignature(
                'app_verify_email',
                $charlie->getId()->toRfc4122(),
                $charlie->getEmail(),
                ['id' => $charlie->getId()->toRfc4122()],
            )
            ->getSignedUrl();

        // Replace the expires value with a past timestamp — invalidates the HMAC signature
        $tamperedUrl = preg_replace('/expires=\d+/', 'expires=1', $signedUrl);

        $client->request('GET', $tamperedUrl);

        $this->assertResponseRedirects('/login');
        $client->followRedirect();
        $this->assertSelectorExists('.flash--error');
        $this->assertFalse($charlie->isVerified());
    }

    public function testAlreadyVerifiedUserLinkStillSucceeds(): void
    {
        $client = static::createClient();

        $alice = static::getContainer()->get(UserRepository::class)
            ->findOneBy(['email' => 'alice@swiftchat.app']);

        $signedUrl = static::getContainer()->get(VerifyEmailHelperInterface::class)
            ->generateSignature(
                'app_verify_email',
                $alice->getId()->toRfc4122(),
                $alice->getEmail(),
                ['id' => $alice->getId()->toRfc4122()],
            )
            ->getSignedUrl();

        $client->request('GET', $signedUrl);

        $this->assertResponseRedirects('/login');
        $client->followRedirect();
        $this->assertSelectorTextContains('.flash--success', 'verified');
    }
}
