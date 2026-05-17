<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Enum\PresenceStatus;
use App\Service\PresenceRedisService;
use PHPUnit\Framework\TestCase;
use Predis\Client;

/**
 * In-memory Redis stub — avoids fighting PHPUnit 13's mock builder over __call magic methods.
 */
final class FakeRedisClient extends Client
{
    /** @var array<string, string> */
    private array $strings = [];
    /** @var array<string, int> */
    private array $ttls = [];
    /** @var array<string, array<string, true>> */
    private array $sets = [];

    public function __construct()
    {
    }

    /** @param mixed[] $arguments */
    public function __call($command, $arguments): mixed
    {
        return match ($command) {
            'get' => $this->strings[$arguments[0]] ?? null,
            'setex' => $this->fakeSetex($arguments[0], $arguments[1], $arguments[2]),
            'del' => $this->fakeDel($arguments[0]),
            'sadd' => $this->fakeSadd($arguments[0], $arguments[1]),
            'srem' => $this->fakeSrem($arguments[0], $arguments[1]),
            'smembers' => array_keys($this->sets[$arguments[0]] ?? []),
            'exists' => isset($this->strings[$arguments[0]]) ? 1 : 0,
            default => null,
        };
    }

    public function getString(string $key): ?string
    {
        return $this->strings[$key] ?? null;
    }

    public function getTtl(string $key): ?int
    {
        return $this->ttls[$key] ?? null;
    }

    /** @return string[] */
    public function getSet(string $key): array
    {
        return array_keys($this->sets[$key] ?? []);
    }

    private function fakeSetex(string $key, int $ttl, string $value): void
    {
        $this->strings[$key] = $value;
        $this->ttls[$key] = $ttl;
    }

    private function fakeDel(string $key): int
    {
        $existed = array_key_exists($key, $this->strings);
        unset($this->strings[$key], $this->ttls[$key]);

        return $existed ? 1 : 0;
    }

    /** @param string[] $members */
    private function fakeSadd(string $key, array $members): int
    {
        $added = 0;
        foreach ($members as $m) {
            if (!isset($this->sets[$key][$m])) {
                $this->sets[$key][$m] = true;
                ++$added;
            }
        }

        return $added;
    }

    private function fakeSrem(string $key, string $member): int
    {
        $existed = isset($this->sets[$key][$member]);
        unset($this->sets[$key][$member]);

        return $existed ? 1 : 0;
    }
}

class PresenceRedisServiceTest extends TestCase
{
    private FakeRedisClient $redis;
    private PresenceRedisService $service;

    protected function setUp(): void
    {
        $this->redis = new FakeRedisClient();
        $this->service = new PresenceRedisService($this->redis);
    }

    public function testSetPresenceReturnsTrueWhenKeyWasAbsent(): void
    {
        $changed = $this->service->setPresence('user-123', PresenceStatus::Online);

        $this->assertTrue($changed);
    }

    public function testSetPresenceReturnsTrueWhenStatusChanges(): void
    {
        $this->service->setPresence('user-123', PresenceStatus::Online);
        $changed = $this->service->setPresence('user-123', PresenceStatus::Away);

        $this->assertTrue($changed);
    }

    public function testSetPresenceReturnsFalseWhenStatusUnchanged(): void
    {
        $this->service->setPresence('user-123', PresenceStatus::Online);
        $changed = $this->service->setPresence('user-123', PresenceStatus::Online);

        $this->assertFalse($changed);
    }

    public function testSetPresenceStoresValueWithOnlineTtl(): void
    {
        $this->service->setPresence('user-123', PresenceStatus::Online);

        $this->assertSame('online', $this->redis->getString('presence:user-123'));
        $this->assertSame(90, $this->redis->getTtl('presence:user-123'));
    }

    public function testSetPresenceStoresValueWithAwayTtl(): void
    {
        $this->service->setPresence('user-123', PresenceStatus::Away);

        $this->assertSame('away', $this->redis->getString('presence:user-123'));
        $this->assertSame(180, $this->redis->getTtl('presence:user-123'));
    }

    public function testSetPresenceAddsUserToOnlineSet(): void
    {
        $this->service->setPresence('user-123', PresenceStatus::Online);

        $this->assertContains('user-123', $this->redis->getSet('presence:online_set'));
    }

    public function testSetPresenceThrowsForOfflineStatus(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->setPresence('user-123', PresenceStatus::Offline);
    }

    public function testGetPresenceReturnsOfflineWhenKeyAbsent(): void
    {
        $this->assertSame(PresenceStatus::Offline, $this->service->getPresence('user-123'));
    }

    public function testGetPresenceReturnsStoredStatus(): void
    {
        $this->service->setPresence('user-123', PresenceStatus::Away);

        $this->assertSame(PresenceStatus::Away, $this->service->getPresence('user-123'));
    }

    public function testRemovePresenceDeletesKey(): void
    {
        $this->service->setPresence('user-123', PresenceStatus::Online);
        $this->service->removePresence('user-123');

        $this->assertNull($this->redis->getString('presence:user-123'));
    }

    public function testRemovePresenceEvictsFromSet(): void
    {
        $this->service->setPresence('user-123', PresenceStatus::Online);
        $this->service->removePresence('user-123');

        $this->assertNotContains('user-123', $this->redis->getSet('presence:online_set'));
    }

    public function testEvictFromSetDoesNotDeleteKey(): void
    {
        $this->service->setPresence('user-123', PresenceStatus::Online);
        $this->service->evictFromSet('user-123');

        $this->assertSame('online', $this->redis->getString('presence:user-123'));
        $this->assertNotContains('user-123', $this->redis->getSet('presence:online_set'));
    }

    public function testGetStaleMemberIdsReturnsUsersWhoseKeyExpired(): void
    {
        $this->service->setPresence('user-1', PresenceStatus::Online);
        $this->service->setPresence('user-2', PresenceStatus::Online);
        // Simulate expiry for user-2 by deleting its key directly
        $this->service->removePresence('user-2');
        // Re-add to set without key (simulates what happens when only the TTL expires)
        $this->redis->__call('sadd', ['presence:online_set', ['user-2']]);

        $stale = $this->service->getStaleMemberIds();

        $this->assertSame(['user-2'], $stale);
    }

    public function testGetStaleMemberIdsReturnsEmptyWhenAllKeysAlive(): void
    {
        $this->service->setPresence('user-1', PresenceStatus::Online);
        $this->service->setPresence('user-2', PresenceStatus::Away);

        $this->assertSame([], $this->service->getStaleMemberIds());
    }
}
