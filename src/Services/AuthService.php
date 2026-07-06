<?php

declare(strict_types=1);

namespace AvatarTok\Services;

use AvatarTok\Core\Database;
use AvatarTok\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Ramsey\Uuid\Uuid;

class AuthService
{
    private string $jwtSecret;
    private int    $jwtExpiry;
    private int    $refreshExpiry;

    public function __construct()
    {
        $this->jwtSecret     = $_ENV['JWT_SECRET'];
        $this->jwtExpiry     = (int) $_ENV['JWT_EXPIRY'];
        $this->refreshExpiry = (int) $_ENV['JWT_REFRESH_EXPIRY'];
    }

    public function registerUser(array $data): User
    {
        $id = Uuid::uuid4()->toString();
        $isLocal = ($_ENV['APP_ENV'] ?? 'production') === 'local';

        Database::insert('users', [
            'id'            => $id,
            'username'      => $data['username'],
            'email'         => $data['email'],
            'password_hash' => password_hash($data['password'], PASSWORD_ARGON2ID),
            'birthdate'     => $data['birthdate'],
            'country'       => strtoupper($data['country']),
            'role'          => 'user',
            'status'        => 'active',
            'trust_score'   => 50,
            'email_verified_at' => $isLocal ? date('Y-m-d H:i:s') : null,
            'created_at'    => date('Y-m-d H:i:s'),
        ]);

        return User::findById($id);
    }

    public function attemptLogin(string $email, string $password, string $ip): array
    {
        $user = User::findByEmail($email);

        if (!$user) {
            $this->recordFailedAttempt($email, $ip);
            return ['success' => false, 'message' => 'Invalid credentials.'];
        }

        if (!password_verify($password, $user->password_hash)) {
            $this->recordFailedAttempt($email, $ip);
            return ['success' => false, 'message' => 'Invalid credentials.'];
        }

        if ($user->email_verified_at === null) {
            return ['success' => false, 'message' => 'Please verify your email before logging in.'];
        }

        // Rehash if needed (algorithm upgrade)
        if (password_needs_rehash($user->password_hash, PASSWORD_ARGON2ID)) {
            Database::update('users', [
                'password_hash' => password_hash($password, PASSWORD_ARGON2ID),
            ], ['id' => $user->id]);
        }

        Database::update('users', ['last_login_at' => date('Y-m-d H:i:s')], ['id' => $user->id]);

        return [
            'success' => true,
            'user'    => $user,
            'tokens'  => $this->issueTokens($user->id),
        ];
    }

    public function issueTokens(string $userId): array
    {
        $now           = time();
        $refreshId     = Uuid::uuid4()->toString();

        $accessPayload = [
            'sub' => $userId,
            'iat' => $now,
            'exp' => $now + $this->jwtExpiry,
            'typ' => 'access',
        ];

        $refreshPayload = [
            'sub' => $userId,
            'jti' => $refreshId,
            'iat' => $now,
            'exp' => $now + $this->refreshExpiry,
            'typ' => 'refresh',
        ];

        $access  = JWT::encode($accessPayload,  $this->jwtSecret, 'HS256');
        $refresh = JWT::encode($refreshPayload, $this->jwtSecret, 'HS256');

        // Persist refresh token for revocation support
        Database::insert('refresh_tokens', [
            'id'         => $refreshId,
            'user_id'    => $userId,
            'expires_at' => date('Y-m-d H:i:s', $now + $this->refreshExpiry),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return ['access' => $access, 'refresh' => $refresh];
    }

    public function validateToken(string $token): ?object
    {
        try {
            $payload = JWT::decode($token, new Key($this->jwtSecret, 'HS256'));

            if ($payload->typ !== 'access') {
                return null;
            }

            $cache  = new CacheService();
            $revKey = 'revoked_token:' . sha1($token);
            if ($cache->exists($revKey)) {
                return null;
            }

            return User::findById($payload->sub);
        } catch (\Throwable) {
            return null;
        }
    }

    public function refreshTokens(string $refreshToken): ?array
    {
        try {
            $payload = JWT::decode($refreshToken, new Key($this->jwtSecret, 'HS256'));

            if ($payload->typ !== 'refresh') {
                return null;
            }

            $stored = Database::fetchOne(
                'SELECT * FROM refresh_tokens WHERE id = ? AND user_id = ?',
                [$payload->jti, $payload->sub]
            );

            if (!$stored || $stored->revoked_at !== null) {
                return null;
            }

            // Rotate: revoke old, issue new
            Database::update('refresh_tokens', ['revoked_at' => date('Y-m-d H:i:s')], ['id' => $payload->jti]);

            return $this->issueTokens($payload->sub);
        } catch (\Throwable) {
            return null;
        }
    }

    public function revokeToken(string $accessToken): void
    {
        try {
            $payload = JWT::decode($accessToken, new Key($this->jwtSecret, 'HS256'));
            $ttl     = $payload->exp - time();

            if ($ttl > 0) {
                (new CacheService())->set(
                    'revoked_token:' . sha1($accessToken),
                    1,
                    $ttl
                );
            }
        } catch (\Throwable) {
            // Token already invalid — nothing to revoke
        }
    }

    public function verifyEmail(string $token): bool
    {
        $record = Database::fetchOne(
            "SELECT * FROM email_verifications WHERE token = ? AND expires_at > NOW() AND used_at IS NULL",
            [$token]
        );

        if (!$record) {
            return false;
        }

        Database::beginTransaction();
        try {
            Database::update('users', ['email_verified_at' => date('Y-m-d H:i:s')], ['id' => $record->user_id]);
            Database::update('email_verifications', ['used_at' => date('Y-m-d H:i:s')], ['id' => $record->id]);
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }

        return true;
    }

    public function verifyPhone(string $userId, string $code): bool
    {
        $record = Database::fetchOne(
            "SELECT * FROM phone_verifications WHERE user_id = ? AND code = ? AND expires_at > NOW() AND used_at IS NULL",
            [$userId, $code]
        );

        if (!$record) {
            return false;
        }

        Database::update('users', ['phone_verified_at' => date('Y-m-d H:i:s')], ['id' => $userId]);
        Database::update('phone_verifications', ['used_at' => date('Y-m-d H:i:s')], ['id' => $record->id]);

        return true;
    }

    public function sendPasswordReset(string $email): void
    {
        $user = User::findByEmail($email);
        if (!$user) {
            return; // Silent — no enumeration
        }

        $token = bin2hex(random_bytes(32));
        Database::insert('password_resets', [
            'id'         => Uuid::uuid4()->toString(),
            'user_id'    => $user->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+1 hour')),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        (new NotificationService())->sendPasswordResetEmail($user, $token);
    }

    public function resetPassword(string $token, string $newPassword): bool
    {
        $record = Database::fetchOne(
            "SELECT * FROM password_resets WHERE token_hash = ? AND expires_at > NOW() AND used_at IS NULL",
            [hash('sha256', $token)]
        );

        if (!$record) {
            return false;
        }

        Database::update('users', [
            'password_hash' => password_hash($newPassword, PASSWORD_ARGON2ID),
        ], ['id' => $record->user_id]);

        Database::update('password_resets', ['used_at' => date('Y-m-d H:i:s')], ['id' => $record->id]);

        return true;
    }

    private function recordFailedAttempt(string $email, string $ip): void
    {
        Database::insert('login_attempts', [
            'id'         => Uuid::uuid4()->toString(),
            'email'      => $email,
            'ip_address' => $ip,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
