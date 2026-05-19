<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Enum\PresenceStatus;
use App\Service\MercurePublisher;
use App\Service\PresenceService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PresenceServiceTest extends TestCase
{
    private MercurePublisher&MockObject $publisher;
    private PresenceService $service;

    protected function setUp(): void
    {
        $this->publisher = $this->createMock(MercurePublisher::class);
        $this->service = new PresenceService($this->publisher);
    }

    public function testUpdateStatusPublishesOnlineToMercure(): void
    {
        $this->publisher
            ->expects($this->once())
            ->method('publishPresence')
            ->with('user-uuid-123', PresenceStatus::Online);

        $this->service->updateStatus('user-uuid-123', PresenceStatus::Online);
    }

    public function testUpdateStatusPublishesOfflineToMercure(): void
    {
        $this->publisher
            ->expects($this->once())
            ->method('publishPresence')
            ->with('user-uuid-123', PresenceStatus::Offline);

        $this->service->updateStatus('user-uuid-123', PresenceStatus::Offline);
    }

    public function testUpdateStatusPublishesAwayToMercure(): void
    {
        $this->publisher
            ->expects($this->once())
            ->method('publishPresence')
            ->with('user-uuid-123', PresenceStatus::Away);

        $this->service->updateStatus('user-uuid-123', PresenceStatus::Away);
    }
}
