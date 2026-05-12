<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\User;
use App\Enum\PresenceStatus;
use App\Service\MercurePublisher;
use App\Service\PresenceService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PresenceServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private MercurePublisher&MockObject $publisher;
    private PresenceService $service;

    protected function setUp(): void
    {
        $this->em        = $this->createMock(EntityManagerInterface::class);
        $this->publisher = $this->createMock(MercurePublisher::class);
        $this->service   = new PresenceService($this->em, $this->publisher);
    }

    public function testUpdateStatusSetsPresenceOnUser(): void
    {
        $user = $this->makeUser();

        $this->em->expects($this->once())->method('flush');
        $this->publisher->expects($this->once())->method('publishPresence')->with($user);

        $this->service->updateStatus($user, PresenceStatus::Online);

        $this->assertSame(PresenceStatus::Online, $user->getPresenceStatus());
    }

    public function testUpdateStatusUpdatesLastSeenAt(): void
    {
        $user = $this->makeUser();
        $before = new \DateTimeImmutable();

        $this->em->expects($this->once())->method('flush');
        $this->publisher->expects($this->once())->method('publishPresence');

        $this->service->updateStatus($user, PresenceStatus::Offline);

        $this->assertNotNull($user->getLastSeenAt());
        $this->assertGreaterThanOrEqual($before, $user->getLastSeenAt());
    }

    public function testUpdateStatusFlushesEntityManager(): void
    {
        $user = $this->makeUser();

        $this->em->expects($this->once())->method('flush');
        $this->publisher->expects($this->once())->method('publishPresence');

        $this->service->updateStatus($user, PresenceStatus::Away);
    }

    public function testUpdateStatusPublishesToMercure(): void
    {
        $user = $this->makeUser();

        $this->em->expects($this->once())->method('flush');
        $this->publisher
            ->expects($this->once())
            ->method('publishPresence')
            ->with($user);

        $this->service->updateStatus($user, PresenceStatus::Online);
    }

    public function testMarkOnlineSetsOnlineStatus(): void
    {
        $user = $this->makeUser();

        $this->em->expects($this->once())->method('flush');
        $this->publisher->expects($this->once())->method('publishPresence');

        $this->service->markOnline($user);

        $this->assertSame(PresenceStatus::Online, $user->getPresenceStatus());
    }

    public function testMarkOfflineSetsOfflineStatus(): void
    {
        $user = $this->makeUser();
        $user->setPresenceStatus(PresenceStatus::Online);

        $this->em->expects($this->once())->method('flush');
        $this->publisher->expects($this->once())->method('publishPresence');

        $this->service->markOffline($user);

        $this->assertSame(PresenceStatus::Offline, $user->getPresenceStatus());
    }

    public function testMarkAwaySetsAwayStatus(): void
    {
        $user = $this->makeUser();

        $this->em->expects($this->once())->method('flush');
        $this->publisher->expects($this->once())->method('publishPresence');

        $this->service->markAway($user);

        $this->assertSame(PresenceStatus::Away, $user->getPresenceStatus());
    }

    private function makeUser(): User
    {
        $user = new User();
        $user->setUsername('alice');
        $user->setEmail('alice@example.com');
        $user->setPassword('hashed');

        return $user;
    }
}
