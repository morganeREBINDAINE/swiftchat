<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public const USER_ADMIN = 'user_admin';
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
        // admin — verified, ROLE_ADMIN
        $admin = $this->makeUser(
            username: 'admin',
            email: 'admin@swiftchat.app',
            verified: true,
            roles: ['ROLE_ADMIN'],
        );

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

        $manager->persist($admin);
        $manager->persist($alice);
        $manager->persist($bob);
        $manager->persist($charlie);
        $manager->flush();

        $this->addReference(self::USER_ADMIN, $admin);
        $this->addReference(self::USER_ALICE, $alice);
        $this->addReference(self::USER_BOB, $bob);
        $this->addReference(self::USER_CHARLIE, $charlie);

        // Demo conversation with sample messages for portfolio presentation
        $conv = new Conversation($alice, $bob);
        $manager->persist($conv);

        $demoMessages = [
            [$alice, 'Hey Bob! Have you tried SwiftChat yet?'],
            [$bob, 'Hi Alice! Just signed up — looks great so far!'],
            [$alice, 'It has real-time messaging with Mercure, typing indicators, and presence status.'],
            [$bob, 'Nice! What\'s the stack?'],
            [$alice, 'Symfony 7 + FrankenPHP + PostgreSQL + Redis. Full Docker setup.'],
            [$bob, 'Impressive. Any async features?'],
            [$alice, 'Yes — email notifications for unread messages after 5 minutes, via Symfony Messenger.'],
            [$bob, 'That\'s a solid portfolio project. I can see you put a lot of work into this!'],
        ];

        foreach ($demoMessages as [$sender, $content]) {
            $manager->persist(new Message($conv, $sender, $content));
        }

        $manager->flush();
    }

    /** @param string[] $roles */
    private function makeUser(string $username, string $email, bool $verified, array $roles = []): User
    {
        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setPassword($this->hasher->hashPassword($user, self::DEFAULT_PASSWORD));
        $user->setIsVerified($verified);
        if ([] !== $roles) {
            $user->setRoles($roles);
        }

        return $user;
    }
}
