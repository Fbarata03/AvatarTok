<?php

declare(strict_types=1);

namespace AvatarTok\Middleware;

use AvatarTok\Core\Request;
use AvatarTok\Core\Response;
use AvatarTok\Services\AuthService;

class AuthMiddleware
{
    public function process(Request $request): ?Response
    {
        $token = $request->getBearerToken();

        if (!$token) {
            return Response::error('Authentication required.', 401);
        }

        $user = (new AuthService())->validateToken($token);

        if (!$user) {
            return Response::error('Invalid or expired token.', 401);
        }

        if ($user->status === 'banned') {
            return Response::error('Your account has been permanently suspended.', 403);
        }

        if ($user->status === 'suspended') {
            return Response::error('Your account is temporarily suspended.', 403);
        }

        $request->setAuthUser($user);
        return null;
    }
}
