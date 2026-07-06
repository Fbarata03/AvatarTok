<?php

declare(strict_types=1);

namespace AvatarTok\Controllers\Social;

use AvatarTok\Core\Database;
use AvatarTok\Core\Request;
use AvatarTok\Core\Response;
use Ramsey\Uuid\Uuid;

class NotificationController
{
    public function list(Request $request): Response
    {
        $userId = $request->user()->id;
        $page   = max((int) $request->query('page', 1), 1);
        $limit  = min((int) $request->query('limit', 30), 100);

        $total = (int) Database::fetchOne(
            "SELECT COUNT(*) AS c FROM notifications WHERE user_id = ?", [$userId]
        )->c;

        $notifs = Database::fetchAll(
            "SELECT n.*, u.username AS actor_username, av.avatar_url AS actor_avatar
             FROM notifications n
             LEFT JOIN users u ON u.id = n.actor_id
             LEFT JOIN avatars av ON av.user_id = n.actor_id
             WHERE n.user_id = ? ORDER BY n.created_at DESC LIMIT ? OFFSET ?",
            [$userId, $limit, ($page - 1) * $limit]
        );

        return Response::paginated($notifs, $total, $page, $limit);
    }

    public function markRead(Request $request): Response
    {
        Database::update('notifications', ['read_at' => date('Y-m-d H:i:s')],
            ['id' => $request->routeParam('notifId'), 'user_id' => $request->user()->id]);

        return Response::noContent();
    }

    public function markAllRead(Request $request): Response
    {
        Database::query(
            "UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL",
            [$request->user()->id]
        );

        return Response::ok(null, 'All notifications marked as read.');
    }

    public function delete(Request $request): Response
    {
        Database::query(
            "DELETE FROM notifications WHERE id = ? AND user_id = ?",
            [$request->routeParam('notifId'), $request->user()->id]
        );

        return Response::noContent();
    }

    public function preferences(Request $request): Response
    {
        $prefs = Database::fetchOne(
            "SELECT * FROM notification_preferences WHERE user_id = ?",
            [$request->user()->id]
        );

        if (!$prefs) {
            return Response::ok($this->defaultPreferences());
        }

        return Response::ok($prefs);
    }

    public function updatePreferences(Request $request): Response
    {
        $userId = $request->user()->id;
        $data   = $request->only([
            'likes', 'comments', 'follows', 'mentions',
            'gifts', 'live_start', 'system', 'push_enabled', 'email_enabled',
        ]);

        Database::query(
            "INSERT INTO notification_preferences (user_id, " . implode(', ', array_keys($data)) . ")
             VALUES (?, " . implode(', ', array_fill(0, count($data), '?')) . ")
             ON DUPLICATE KEY UPDATE " . implode(', ', array_map(fn($k) => "{$k} = VALUES({$k})", array_keys($data))),
            [$userId, ...array_values($data)]
        );

        return Response::ok(null, 'Preferences updated.');
    }

    public function registerPushToken(Request $request): Response
    {
        $data = $request->validate([
            'platform' => 'required',
            'token'    => 'required',
        ]);

        Database::query(
            "INSERT INTO push_tokens (id, user_id, platform, token) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE token = VALUES(token)",
            [Uuid::uuid4()->toString(), $request->user()->id, $data['platform'], $data['token']]
        );

        return Response::ok(null, 'Push token registered.');
    }

    private function defaultPreferences(): array
    {
        return [
            'likes'        => true,
            'comments'     => true,
            'follows'      => true,
            'mentions'     => true,
            'gifts'        => true,
            'live_start'   => true,
            'system'       => true,
            'push_enabled' => true,
            'email_enabled'=> false,
        ];
    }
}
