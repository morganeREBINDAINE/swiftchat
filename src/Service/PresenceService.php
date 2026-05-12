<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Enum\PresenceStatus;
use Doctrine\ORM\EntityManagerInterface;

class PresenceService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MercurePublisher $publisher,
    ) {}

    public function markOnline(User $user): void
    {
        $this->updateStatus($user, PresenceStatus::Online);
    }

    public function markOffline(User $user): void
    {
        $this->updateStatus($user, PresenceStatus::Offline);
    }

    public function markAway(User $user): void
    {
        $this->updateStatus($user, PresenceStatus::Away);
    }

    public function updateStatus(User $user, PresenceStatus $status): void
    {
        $user->setPresenceStatus($status);
        $user->setLastSeenAt(new \DateTimeImmutable());

        $this->em->flush();
        $this->publisher->publishPresence($user);
    }
}
