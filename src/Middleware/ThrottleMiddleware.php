<?php

declare(strict_types=1);

namespace AvatarTok\Middleware;

use AvatarTok\Core\Request;
use AvatarTok\Core\Response;
use AvatarTok\Services\CacheService;

class ThrottleMiddleware
{
    // Strict throttle for sensitive routes (login, register)
    private const MAX_ATTEMPTS = 5;
    private const WINDOW       = 300; // 5 minutes

    public function process(Request $request): ?Response
    {
        if (($_ENV['APP_ENV'] ?? 'production') === 'local') {
            return null;
        }

        try {
            $key     = 'throttle:' . sha1($request->ip() . $request->getUri());
            $cache   = new CacheService();
            $current = (int) $cache->get($key);

            if ($current >= self::MAX_ATTEMPTS) {
                return Response::error(
                    'Too many attempts. Try again in ' . self::WINDOW . ' seconds.',
                    429
                )->withHeader('Retry-After', (string) self::WINDOW);
            }

            if ($current === 0) {
                $cache->set($key, 1, self::WINDOW);
            } else {
                $cache->increment($key);
            }
        } catch (\Throwable) {
            // Redis unavailable — allow request through
        }

        return null;
    }
}
