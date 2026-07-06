<?php

declare(strict_types=1);

namespace AvatarTok\Services;

use AvatarTok\Core\Database;
use Ramsey\Uuid\Uuid;

class NotificationService
{
    public function sendEmailVerification(object $user): void
    {
        $token = bin2hex(random_bytes(32));

        Database::insert('email_verifications', [
            'id'         => Uuid::uuid4()->toString(),
            'user_id'    => $user->id,
            'token'      => $token,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+24 hours')),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // In production: queue email job
        // Queue::push('send_email', [
        //     'to'       => $user->email,
        //     'template' => 'verify_email',
        //     'vars'     => ['verify_url' => "{$_ENV['APP_URL']}/verify?token={$token}"],
        // ]);
    }

    public function sendPasswordResetEmail(object $user, string $token): void
    {
        // Queue::push('send_email', ['to' => $user->email, 'template' => 'reset_password', ...]);
    }

    public function sendModerationNotice(string $userId, string $type, string $reason): void
    {
        Database::insert('notifications', [
            'id'      => Uuid::uuid4()->toString(),
            'user_id' => $userId,
            'type'    => "moderation.{$type}",
            'payload' => json_encode(['reason' => $reason]),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $this->pushNotify($userId, [
            'title' => 'Account Notice',
            'body'  => "Your account has received a {$type} for: {$reason}",
        ]);
    }

    public function notifyFollow(string $followedId, string $followerId): void
    {
        Database::insert('notifications', [
            'id'          => Uuid::uuid4()->toString(),
            'user_id'     => $followedId,
            'type'        => 'follow',
            'actor_id'    => $followerId,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    public function notifyLike(string $ownerId, string $actorId, string $videoId): void
    {
        Database::insert('notifications', [
            'id'          => Uuid::uuid4()->toString(),
            'user_id'     => $ownerId,
            'type'        => 'like',
            'actor_id'    => $actorId,
            'entity_id'   => $videoId,
            'entity_type' => 'video',
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    private function pushNotify(string $userId, array $payload): void
    {
        $tokens = Database::fetchAll(
            "SELECT platform, token FROM push_tokens WHERE user_id = ?",
            [$userId]
        );

        foreach ($tokens as $t) {
            // Dispatch platform-specific push (FCM/APNS) via queue
        }
    }
}
