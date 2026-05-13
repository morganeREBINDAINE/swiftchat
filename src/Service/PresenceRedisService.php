<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\PresenceStatus;
use Predis\Client;

class PresenceRedisService
{
    private const KEY_PATTERN = 'presence:%s';
    private const ONLINE_SET  = 'presence:online_set';
    private const TTL_ONLINE  = 90;   // seconds
    private const TTL_AWAY    = 180;  // seconds

    public function __construct(private readonly Client $redis) {}

    /**
     * Stores presence in Redis with appropriate TTL and adds the user to the online set.
     * Returns true if the stored status changed (new value ≠ previous value).
     */
    public function setPresence(string $userId, PresenceStatus $status): bool
    {
        $key      = $this->key($userId);
        $previous = $this->redis->get($key);

        $ttl = match ($status) {
            PresenceStatus::Online  => self::TTL_ONLINE,
            PresenceStatus::Away    => self::TTL_AWAY,
            PresenceStatus::Offline => throw new \InvalidArgumentException(
                'Use removePresence() to mark a user offline.'
            ),
        };

        $this->redis->setex($key, $ttl, $status->value);
        $this->redis->sadd(self::ONLINE_SET, [$userId]);

        return $previous !== $status->value;
    }

    public function getPresence(string $userId): PresenceStatus
    {
        $value = $this->redis->get($this->key($userId));

        return $value !== null ? PresenceStatus::from($value) : PresenceStatus::Offline;
    }

    /**
     * Deletes the presence key and removes the user from the online set.
     */
    public function removePresence(string $userId): void
    {
        $this->redis->del($this->key($userId));
        $this->redis->srem(self::ONLINE_SET, $userId);
    }

    /**
     * Removes a user ID from the online set without touching the (already expired) key.
     * Used by the cleanup command after a TTL expiry is detected.
     */
    public function evictFromSet(string $userId): void
    {
        $this->redis->srem(self::ONLINE_SET, $userId);
    }

    /**
     * Returns the IDs of users in the online set whose presence key has expired.
     *
     * @return string[]
     */
    public function getStaleMemberIds(): array
    {
        $ids   = $this->redis->smembers(self::ONLINE_SET) ?? [];
        $stale = [];

        foreach ($ids as $id) {
            if ($this->redis->exists(sprintf(self::KEY_PATTERN, $id)) === 0) {
                $stale[] = $id;
            }
        }

        return $stale;
    }

    private function key(string $userId): string
    {
        return sprintf(self::KEY_PATTERN, $userId);
    }
}
