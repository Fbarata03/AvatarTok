<?php

declare(strict_types=1);

namespace AvatarTok\Services;

use Predis\Client as RedisClient;

class CacheService
{
    private static ?RedisClient $client = null;

    private function client(): RedisClient
    {
        if (self::$client !== null) {
            return self::$client;
        }

        self::$client = new RedisClient([
            'scheme'   => 'tcp',
            'host'     => $_ENV['REDIS_HOST'],
            'port'     => (int) ($_ENV['REDIS_PORT'] ?? 6379),
            'password' => $_ENV['REDIS_PASSWORD'] ?: null,
            'database' => (int) ($_ENV['REDIS_DB'] ?? 0),
        ]);

        return self::$client;
    }

    public function get(string $key): mixed
    {
        return $this->client()->get($key);
    }

    public function set(string $key, mixed $value, int $ttlSeconds = 3600): void
    {
        $this->client()->setex($key, $ttlSeconds, $value);
    }

    public function delete(string $key): void
    {
        $this->client()->del([$key]);
    }

    public function exists(string $key): bool
    {
        return (bool) $this->client()->exists($key);
    }

    public function increment(string $key): int
    {
        return $this->client()->incr($key);
    }

    public function decrement(string $key): int
    {
        return $this->client()->decr($key);
    }

    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        $cached = $this->get($key);

        if ($cached !== null) {
            return is_string($cached) ? unserialize($cached) : $cached;
        }

        $value = $callback();
        $this->set($key, serialize($value), $ttl);

        return $value;
    }

    public function flush(string $pattern): void
    {
        $keys = $this->client()->keys($pattern);
        if (!empty($keys)) {
            $this->client()->del($keys);
        }
    }

    /** Publish a message to a channel (for WebSocket server coordination) */
    public function publish(string $channel, mixed $message): void
    {
        $this->client()->publish($channel, json_encode($message));
    }
}
