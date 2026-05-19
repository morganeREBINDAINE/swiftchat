<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Conversation;
use App\Entity\User;
use App\Repository\ConversationRepository;
use App\Service\ConversationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class ConversationServiceTest extends TestCase
{
    private ConversationRepository&MockObject $repository;
    private EntityManagerInterface&MockObject $em;
    private ConversationService $service;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(ConversationRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->service = new ConversationService($this->repository, $this->em);
    }

    public function testReturnsExistingConversation(): void
    {
        $alice = $this->makeUser();
        $bob = $this->makeUser();

        $existing = new Conversation($alice, $bob);

        $this->repository
            ->expects($this->once())
            ->method('findBetween')
            ->with($alice, $bob)
            ->willReturn($existing);

        $this->em->expects($this->never())->method('persist');
        $this->em->expects($this->never())->method('flush');

        $result = $this->service->findOrCreate($alice, $bob);

        $this->assertSame($existing, $result);
    }

    public function testCreatesNewConversationWhenNoneExists(): void
    {
        $alice = $this->makeUser();
        $bob = $this->makeUser();

        $this->repository
            ->expects($this->once())
            ->method('findBetween')
            ->with($alice, $bob)
            ->willReturn(null);

        $this->em->expects($this->once())->method('persist')
            ->with($this->isInstanceOf(Conversation::class));
        $this->em->expects($this->once())->method('flush');

        $result = $this->service->findOrCreate($alice, $bob);

        $this->assertInstanceOf(Conversation::class, $result);
        $this->assertTrue($result->hasParticipant($alice));
        $this->assertTrue($result->hasParticipant($bob));
    }

    public function testParticipantOrderIsNormalisedByUuid(): void
    {
        $alice = $this->makeUser();
        $bob = $this->makeUser();

        // Determine which UUID is smaller (will become participant1)
        $aliceFirst = strcmp(
            $alice->getId()->toRfc4122(),
            $bob->getId()->toRfc4122()
        ) < 0;

        $this->repository->expects($this->once())->method('findBetween')->willReturn(null);
        $this->em->expects($this->once())->method('persist')->with($this->isInstanceOf(Conversation::class));
        $this->em->expects($this->once())->method('flush');

        $result = $this->service->findOrCreate($alice, $bob);

        if ($aliceFirst) {
            $this->assertSame($alice, $result->getParticipant1());
            $this->assertSame($bob, $result->getParticipant2());
        } else {
            $this->assertSame($bob, $result->getParticipant1());
            $this->assertSame($alice, $result->getParticipant2());
        }
    }

    public function testNormalisationIsSymmetric(): void
    {
        $alice = $this->makeUser();
        $bob = $this->makeUser();

        $this->repository->expects($this->exactly(2))->method('findBetween')->willReturn(null);
        $this->em->expects($this->exactly(2))->method('persist')->with($this->isInstanceOf(Conversation::class));
        $this->em->expects($this->exactly(2))->method('flush');

        $ab = $this->service->findOrCreate($alice, $bob);
        $ba = $this->service->findOrCreate($bob, $alice);

        $this->assertSame($ab->getParticipant1(), $ba->getParticipant1());
        $this->assertSame($ab->getParticipant2(), $ba->getParticipant2());
    }

    private function makeUser(): User
    {
        $user = new User();
        $user->setUsername('user_'.substr(Uuid::v7()->toRfc4122(), 0, 8));
        $user->setEmail(Uuid::v7()->toRfc4122().'@example.com');
        $user->setPassword('hashed');

        return $user;
    }
}
