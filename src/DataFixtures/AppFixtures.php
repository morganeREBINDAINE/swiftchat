<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public const USER_ALICE = 'user_alice';
    public const USER_BOB = 'user_bob';
    public const USER_CHARLIE = 'user_charlie';

    // Stable plain-text password usable in tests
    public const DEFAULT_PASSWORD = 'Password1!';

    public function __construct(
        private readonly UserPasswordHasherInterface $hasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // alice — verified, for portfolio demo and general auth tests
        $alice = $this->makeUser(
            username: 'alice',
            email: 'alice@swiftchat.app',
            verified: true,
        );

        // bob — verified, second participant for conversation fixtures
        $bob = $this->makeUser(
            username: 'bob',
            email: 'bob@swiftchat.app',
            verified: true,
        );

        // charlie — NOT verified, for testing blocked login on unverified account
        $charlie = $this->makeUser(
            username: 'charlie',
            email: 'charlie@swiftchat.app',
            verified: false,
        );

        $manager->persist($alice);
        $manager->persist($bob);
        $manager->persist($charlie);
        $manager->flush();

        $this->addReference(self::USER_ALICE, $alice);
        $this->addReference(self::USER_BOB, $bob);
        $this->addReference(self::USER_CHARLIE, $charlie);
    }

    private function makeUser(string $username, string $email, bool $verified): User
    {
        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setPassword($this->hasher->hashPassword($user, self::DEFAULT_PASSWORD));
        $user->setIsVerified($verified);

        return $user;
    }
}
