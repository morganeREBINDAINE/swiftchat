<?php

declare(strict_types=1);

namespace App\Messenger\Handler;

use App\Enum\PresenceStatus;
use App\Messenger\Message\PresenceCleanupMessage;
use App\Service\PresenceRedisService;
use App\Service\PresenceService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class PresenceCleanupHandler
{
    public function __construct(
        private readonly PresenceRedisService $presenceRedis,
        private readonly PresenceService $presenceService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(PresenceCleanupMessage $message): void
    {
        $cleaned = 0;
        $failed = 0;

        try {
            $stale = $this->presenceRedis->getStaleMemberIds();
        } catch (\Throwable $e) {
            $this->logger->error('Presence cleanup aborted: could not fetch stale members', [
                'error' => $e->getMessage(),
            ]);

            return;
        }

        foreach ($stale as $id) {
            try {
                $this->presenceRedis->evictFromSet($id);
                $this->presenceService->updateStatus($id, PresenceStatus::Offline);
                ++$cleaned;
            } catch (\Throwable $e) {
                $this->logger->error('Failed to clean up presence entry', [
                    'user_id' => $id,
                    'error' => $e->getMessage(),
                ]);
                ++$failed;
            }
        }

        $this->logger->info('Presence cleanup completed', [
            'cleaned' => $cleaned,
            'failed' => $failed,
        ]);
    }
}
