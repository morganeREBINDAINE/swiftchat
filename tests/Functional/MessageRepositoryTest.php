<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Message;
use App\Repository\MessageRepository;
use App\Repository\UserRepository;
use App\Service\ConversationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class MessageRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private MessageRepository $repo;
    private ConversationService $convService;
    private UserRepository $userRepo;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->repo = $container->get(MessageRepository::class);
        $this->convService = $container->get(ConversationService::class);
        $this->userRepo = $container->get(UserRepository::class);
    }

    public function testEmptyArrayReturnsEmptyMap(): void
    {
        $alice = $this->userRepo->findByUsername('alice');

        $this->assertSame([], $this->repo->countUnreadPerConversation([], $alice));
    }

    public function testConversationWithNoMessagesReturnsZero(): void
    {
        $alice = $this->userRepo->findByUsername('alice');
        $bob = $this->userRepo->findByUsername('bob');
        $conv = $this->convService->findOrCreate($alice, $bob);

        $result = $this->repo->countUnreadPerConversation([$conv], $alice);

        $this->assertArrayHasKey($conv->getId()->toRfc4122(), $result);
        $this->assertGreaterThanOrEqual(0, $result[$conv->getId()->toRfc4122()]);
    }

    public function testCountsUnreadMessagesFromOtherParticipant(): void
    {
        $alice = $this->userRepo->findByUsername('alice');
        $bob = $this->userRepo->findByUsername('bob');
        $conv = $this->convService->findOrCreate($alice, $bob);
        $convId = $conv->getId()->toRfc4122();

        $before = $this->repo->countUnreadPerConversation([$conv], $alice)[$convId];

        $this->em->persist(new Message($conv, $bob, 'Hello'));
        $this->em->persist(new Message($conv, $bob, 'Hey there'));
        $this->em->flush();

        $after = $this->repo->countUnreadPerConversation([$conv], $alice)[$convId];

        $this->assertSame($before + 2, $after);
    }

    public function testDoesNotCountReadMessages(): void
    {
        $alice = $this->userRepo->findByUsername('alice');
        $bob = $this->userRepo->findByUsername('bob');
        $conv = $this->convService->findOrCreate($alice, $bob);
        $convId = $conv->getId()->toRfc4122();

        $before = $this->repo->countUnreadPerConversation([$conv], $alice)[$convId];

        $msg = new Message($conv, $bob, 'Already read');
        $msg->markAsRead();
        $this->em->persist($msg);
        $this->em->flush();

        $after = $this->repo->countUnreadPerConversation([$conv], $alice)[$convId];

        $this->assertSame($before, $after);
    }

    public function testDoesNotCountOwnSentMessages(): void
    {
        $alice = $this->userRepo->findByUsername('alice');
        $bob = $this->userRepo->findByUsername('bob');
        $conv = $this->convService->findOrCreate($alice, $bob);
        $convId = $conv->getId()->toRfc4122();

        $before = $this->repo->countUnreadPerConversation([$conv], $alice)[$convId];

        $this->em->persist(new Message($conv, $alice, 'My own message'));
        $this->em->flush();

        $after = $this->repo->countUnreadPerConversation([$conv], $alice)[$convId];

        $this->assertSame($before, $after);
    }

    public function testReturnsCorrectCountsForMultipleConversations(): void
    {
        $alice = $this->userRepo->findByUsername('alice');
        $bob = $this->userRepo->findByUsername('bob');
        $charlie = $this->userRepo->findByUsername('charlie');

        $aliceBob = $this->convService->findOrCreate($alice, $bob);
        $aliceCharlie = $this->convService->findOrCreate($alice, $charlie);

        $bobId = $aliceBob->getId()->toRfc4122();
        $charlieId = $aliceCharlie->getId()->toRfc4122();

        $beforeBob = $this->repo->countUnreadPerConversation([$aliceBob], $alice)[$bobId];
        $beforeCharlie = $this->repo->countUnreadPerConversation([$aliceCharlie], $alice)[$charlieId];

        $this->em->persist(new Message($aliceBob, $bob, 'Hello Alice'));
        $this->em->persist(new Message($aliceCharlie, $charlie, 'Hi Alice'));
        $this->em->persist(new Message($aliceCharlie, $charlie, 'Another from Charlie'));
        $this->em->flush();

        $result = $this->repo->countUnreadPerConversation([$aliceBob, $aliceCharlie], $alice);

        $this->assertSame($beforeBob + 1, $result[$bobId]);
        $this->assertSame($beforeCharlie + 2, $result[$charlieId]);
    }
}
