<?php

declare(strict_types=1);

namespace AvatarTok\Controllers\Social;

use AvatarTok\Core\Database;
use AvatarTok\Core\Request;
use AvatarTok\Core\Response;
use AvatarTok\Services\NotificationService;

class FriendController
{
    public function __construct(
        private readonly NotificationService $notif = new NotificationService()
    ) {}

    public function list(Request $request): Response
    {
        $userId    = $request->user()->id;
        $followers = Database::fetchAll(
            "SELECT u.id, u.username, u.display_name, av.avatar_url, f.created_at AS followed_at
             FROM follows f JOIN users u ON u.id = f.follower_id
             LEFT JOIN avatars av ON av.user_id = u.id
             WHERE f.following_id = ? ORDER BY f.created_at DESC LIMIT 50",
            [$userId]
        );

        return Response::ok($followers);
    }

    public function follow(Request $request): Response
    {
        $targetId = $request->routeParam('userId');
        $me       = $request->user();

        if ($targetId === $me->id) {
            return Response::error("You can't follow yourself.", 422);
        }

        try {
            Database::insert('follows', [
                'follower_id'  => $me->id,
                'following_id' => $targetId,
                'created_at'   => date('Y-m-d H:i:s'),
            ]);

            $this->notif->notifyFollow($targetId, $me->id);
        } catch (\PDOException $e) {
            if ($e->getCode() !== '23000') throw $e; // Ignore duplicate
        }

        return Response::ok(null, 'Followed.');
    }

    public function unfollow(Request $request): Response
    {
        Database::query(
            "DELETE FROM follows WHERE follower_id = ? AND following_id = ?",
            [$request->user()->id, $request->routeParam('userId')]
        );

        return Response::noContent();
    }

    public function followers(Request $request): Response
    {
        $username = $request->routeParam('username');
        $page     = max((int) $request->query('page', 1), 1);
        $limit    = min((int) $request->query('limit', 30), 100);

        $user = Database::fetchOne("SELECT id FROM users WHERE username = ?", [$username]);
        if (!$user) return Response::error('User not found.', 404);

        $total = (int) Database::fetchOne(
            "SELECT COUNT(*) AS c FROM follows WHERE following_id = ?", [$user->id]
        )->c;

        $list = Database::fetchAll(
            "SELECT u.id, u.username, u.display_name, av.avatar_url
             FROM follows f JOIN users u ON u.id = f.follower_id
             LEFT JOIN avatars av ON av.user_id = u.id
             WHERE f.following_id = ? LIMIT ? OFFSET ?",
            [$user->id, $limit, ($page - 1) * $limit]
        );

        return Response::paginated($list, $total, $page, $limit);
    }

    public function following(Request $request): Response
    {
        $username = $request->routeParam('username');
        $page     = max((int) $request->query('page', 1), 1);
        $limit    = min((int) $request->query('limit', 30), 100);

        $user = Database::fetchOne("SELECT id FROM users WHERE username = ?", [$username]);
        if (!$user) return Response::error('User not found.', 404);

        $total = (int) Database::fetchOne(
            "SELECT COUNT(*) AS c FROM follows WHERE follower_id = ?", [$user->id]
        )->c;

        $list = Database::fetchAll(
            "SELECT u.id, u.username, u.display_name, av.avatar_url
             FROM follows f JOIN users u ON u.id = f.following_id
             LEFT JOIN avatars av ON av.user_id = u.id
             WHERE f.follower_id = ? LIMIT ? OFFSET ?",
            [$user->id, $limit, ($page - 1) * $limit]
        );

        return Response::paginated($list, $total, $page, $limit);
    }

    public function block(Request $request): Response
    {
        $targetId = $request->routeParam('userId');
        $me       = $request->user()->id;

        if ($targetId === $me) {
            return Response::error("You can't block yourself.", 422);
        }

        try {
            Database::insert('blocks', ['blocker_id' => $me, 'blocked_id' => $targetId, 'created_at' => date('Y-m-d H:i:s')]);
        } catch (\PDOException $e) {
            if ($e->getCode() !== '23000') throw $e;
        }

        // Remove mutual follows
        Database::query("DELETE FROM follows WHERE (follower_id = ? AND following_id = ?) OR (follower_id = ? AND following_id = ?)",
            [$me, $targetId, $targetId, $me]);

        return Response::ok(null, 'User blocked.');
    }

    public function unblock(Request $request): Response
    {
        Database::query(
            "DELETE FROM blocks WHERE blocker_id = ? AND blocked_id = ?",
            [$request->user()->id, $request->routeParam('userId')]
        );

        return Response::noContent();
    }

    public function blockedList(Request $request): Response
    {
        $list = Database::fetchAll(
            "SELECT u.id, u.username, u.display_name FROM blocks b JOIN users u ON u.id = b.blocked_id WHERE b.blocker_id = ?",
            [$request->user()->id]
        );

        return Response::ok($list);
    }

    public function mutualFollowers(Request $request): Response
    {
        $targetId = $request->routeParam('userId');
        $myId     = $request->user()->id;

        $mutuals = Database::fetchAll(
            "SELECT u.id, u.username, u.display_name, av.avatar_url
             FROM follows f1
             JOIN follows f2 ON f2.follower_id = f1.follower_id
             JOIN users u ON u.id = f1.follower_id
             LEFT JOIN avatars av ON av.user_id = u.id
             WHERE f1.following_id = ? AND f2.following_id = ? AND f1.follower_id != ?
             LIMIT 30",
            [$myId, $targetId, $myId]
        );

        return Response::ok($mutuals);
    }
}
