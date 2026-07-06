<?php

declare(strict_types=1);

namespace AvatarTok\Middleware;

use AvatarTok\Core\Request;
use AvatarTok\Core\Response;

class AdminMiddleware
{
    private const ADMIN_ROLES = ['admin', 'super_admin', 'moderator'];

    public function process(Request $request): ?Response
    {
        $user = $request->user();

        if (!$user || !in_array($user->role, self::ADMIN_ROLES, true)) {
            return Response::error('Forbidden. Admin access required.', 403);
        }

        return null;
    }
}
