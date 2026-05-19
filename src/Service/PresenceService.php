<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\PresenceStatus;

class PresenceService
{
    public function __construct(private readonly MercurePublisher $publisher)
    {
    }

    public function updateStatus(string $userId, PresenceStatus $status): void
    {
        $this->publisher->publishPresence($userId, $status);
    }
}
