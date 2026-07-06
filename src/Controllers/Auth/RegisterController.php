<?php

declare(strict_types=1);

namespace AvatarTok\Controllers\Auth;

use AvatarTok\Core\Request;
use AvatarTok\Core\Response;
use AvatarTok\Services\AuthService;
use AvatarTok\Services\NotificationService;
use AvatarTok\Models\User;

class RegisterController
{
    public function __construct(
        private readonly AuthService         $auth  = new AuthService(),
        private readonly NotificationService $notif = new NotificationService()
    ) {}

    public function register(Request $request): Response
    {
        $data = $request->validate([
            'username'  => 'required|min:3|max:30',
            'email'     => 'required|email|max:255',
            'password'  => 'required|min:8|max:128',
            'birthdate' => 'required',
            'country'   => 'required|max:2',
        ]);

        // Age gate: must be 13+
        $age = (new \DateTime())->diff(new \DateTime($data['birthdate']))->y;
        if ($age < 13) {
            return Response::error('You must be at least 13 years old to register.', 422);
        }

        if (User::existsByEmail($data['email'])) {
            return Response::error('Email already registered.', 409);
        }

        if (User::existsByUsername($data['username'])) {
            return Response::error('Username already taken.', 409);
        }

        $user = $this->auth->registerUser($data);

        $this->notif->sendEmailVerification($user);

        $tokens = $this->auth->issueTokens($user->id);

        return Response::created([
            'user'          => $user->toPublicArray(),
            'access_token'  => $tokens['access'],
            'refresh_token' => $tokens['refresh'],
            'expires_in'    => (int) $_ENV['JWT_EXPIRY'],
        ], 'Account created. Please verify your email.');
    }

    public function verifyEmail(Request $request): Response
    {
        $data = $request->validate(['token' => 'required']);

        $success = $this->auth->verifyEmail($data['token']);

        if (!$success) {
            return Response::error('Invalid or expired verification token.', 422);
        }

        return Response::ok(null, 'Email verified successfully.');
    }

    public function verifyPhone(Request $request): Response
    {
        $user = $request->user();
        $data = $request->validate(['code' => 'required|min:6|max:6']);

        $success = $this->auth->verifyPhone($user->id, $data['code']);

        if (!$success) {
            return Response::error('Invalid or expired verification code.', 422);
        }

        return Response::ok(null, 'Phone number verified.');
    }
}
