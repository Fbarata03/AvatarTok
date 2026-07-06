<?php

declare(strict_types=1);

namespace AvatarTok\Controllers\Auth;

use AvatarTok\Core\Request;
use AvatarTok\Core\Response;
use AvatarTok\Services\AuthService;

class RefreshTokenController
{
    public function refresh(Request $request): Response
    {
        $data = $request->validate(['refresh_token' => 'required']);

        $tokens = (new AuthService())->refreshTokens($data['refresh_token']);

        if (!$tokens) {
            return Response::error('Invalid or expired refresh token.', 401);
        }

        return Response::ok([
            'access_token'  => $tokens['access'],
            'refresh_token' => $tokens['refresh'],
            'expires_in'    => (int) $_ENV['JWT_EXPIRY'],
        ]);
    }
}
