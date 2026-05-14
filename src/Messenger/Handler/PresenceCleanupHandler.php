<?php

declare(strict_types=1);

namespace App\Messenger\Handler;

use App\Enum\PresenceStatus;
use App\Messenger\Message\PresenceCleanupMessage;
use App\Service\PresenceRedisService;
use App\Service\PresenceService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class PresenceCleanupHandler
{
    public function __construct(
        private readonly PresenceRedisService $presenceRedis,
        private readonly PresenceService $presenceService,
    ) {
    }

    public function __invoke(PresenceCleanupMessage $message): void
    {
        foreach ($this->presenceRedis->getStaleMemberIds() as $id) {
            $this->presenceRedis->evictFromSet($id);
            $this->presenceService->updateStatus($id, PresenceStatus::Offline);
        }
    }
}
