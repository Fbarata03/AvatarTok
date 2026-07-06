<?php

declare(strict_types=1);

namespace AvatarTok\Controllers\Auth;

use AvatarTok\Core\Request;
use AvatarTok\Core\Response;
use AvatarTok\Services\AuthService;
use AvatarTok\Services\NotificationService;

class LoginController
{
    public function __construct(
        private readonly AuthService         $auth  = new AuthService(),
        private readonly NotificationService $notif = new NotificationService()
    ) {}

    public function login(Request $request): Response
    {
        $data = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $result = $this->auth->attemptLogin($data['email'], $data['password'], $request->ip());

        if (!$result['success']) {
            return Response::error($result['message'], 401);
        }

        return Response::ok([
            'user'          => $result['user']->toPublicArray(),
            'access_token'  => $result['tokens']['access'],
            'refresh_token' => $result['tokens']['refresh'],
            'expires_in'    => (int) $_ENV['JWT_EXPIRY'],
        ], 'Login successful.');
    }

    public function logout(Request $request): Response
    {
        $token = $request->getBearerToken();
        $this->auth->revokeToken($token);

        return Response::ok(null, 'Logged out successfully.');
    }

    public function forgotPassword(Request $request): Response
    {
        $data = $request->validate(['email' => 'required|email']);

        // Always return success to prevent email enumeration
        $this->auth->sendPasswordReset($data['email']);

        return Response::ok(
            null,
            'If this email is registered, a reset link has been sent.'
        );
    }

    public function resetPassword(Request $request): Response
    {
        $data = $request->validate([
            'token'    => 'required',
            'password' => 'required|min:8|max:128',
        ]);

        $success = $this->auth->resetPassword($data['token'], $data['password']);

        if (!$success) {
            return Response::error('Invalid or expired reset token.', 422);
        }

        return Response::ok(null, 'Password reset successful. Please login.');
    }
}
