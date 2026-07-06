<?php

declare(strict_types=1);

namespace AvatarTok\Middleware;

use AvatarTok\Core\Request;
use AvatarTok\Core\Response;

class CorsMiddleware
{
    private const ALLOWED_ORIGINS = [
        'https://avatartok.com',
        'https://www.avatartok.com',
        'https://creator.avatartok.com',
    ];

    public function process(Request $request): ?Response
    {
        $origin = $request->getHeader('ORIGIN');

        $allowedOrigin = in_array($origin, self::ALLOWED_ORIGINS, true)
            ? $origin
            : ($_ENV['APP_ENV'] === 'local' ? '*' : '');

        header("Access-Control-Allow-Origin: {$allowedOrigin}");
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Device-Id');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');

        if ($request->getMethod() === 'OPTIONS') {
            $response = new Response(null, 204);
            $response->send();
            exit;
        }

        return null;
    }
}
