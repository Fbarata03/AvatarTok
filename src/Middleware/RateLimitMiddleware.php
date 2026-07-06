<?php

declare(strict_types=1);

namespace AvatarTok\Middleware;

use AvatarTok\Core\Request;
use AvatarTok\Core\Response;
use AvatarTok\Services\CacheService;

class RateLimitMiddleware
{
    private int $maxRequests;
    private int $windowSeconds;

    public function __construct()
    {
        $this->maxRequests   = (int) ($_ENV['RATE_LIMIT_REQUESTS'] ?? 100);
        $this->windowSeconds = (int) ($_ENV['RATE_LIMIT_WINDOW']   ?? 60);
    }

    public function process(Request $request): ?Response
    {
        if (($_ENV['APP_ENV'] ?? 'production') === 'local') {
            return null;
        }

        try {
            $key     = 'rate_limit:' . $request->ip();
            $cache   = new CacheService();
            $current = (int) $cache->get($key);

            if ($current >= $this->maxRequests) {
                return Response::error('Too many requests. Please slow down.', 429)
                    ->withHeader('X-RateLimit-Limit',     (string) $this->maxRequests)
                    ->withHeader('X-RateLimit-Remaining', '0')
                    ->withHeader('Retry-After',            (string) $this->windowSeconds);
            }

            if ($current === 0) {
                $cache->set($key, 1, $this->windowSeconds);
            } else {
                $cache->increment($key);
            }

            header('X-RateLimit-Limit: '     . $this->maxRequests);
            header('X-RateLimit-Remaining: ' . ($this->maxRequests - $current - 1));
        } catch (\Throwable) {
            // Redis unavailable — allow request through without rate limiting
        }

        return null;
    }
}
