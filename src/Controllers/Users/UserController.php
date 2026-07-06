<?php

declare(strict_types=1);

namespace AvatarTok\Controllers\Users;

use AvatarTok\Core\Database;
use AvatarTok\Core\Request;
use AvatarTok\Core\Response;
use AvatarTok\Models\User;

class UserController
{
    public function me(Request $request): Response
    {
        $user   = $request->user();
        $avatar = Database::fetchOne("SELECT * FROM avatars WHERE user_id = ?", [$user->id]);
        $wallet = Database::fetchOne("SELECT balance, pending_balance FROM wallets WHERE user_id = ?", [$user->id]);
        $stats  = Database::fetchOne(
            "SELECT COUNT(*) AS videos, SUM(view_count) AS views FROM videos WHERE author_id = ? AND status = 'public'",
            [$user->id]
        );

        $followers  = (int) Database::fetchOne("SELECT COUNT(*) AS c FROM follows WHERE following_id = ?", [$user->id])->c;
        $following  = (int) Database::fetchOne("SELECT COUNT(*) AS c FROM follows WHERE follower_id = ?", [$user->id])->c;

        return Response::ok([
            'user'      => $user->toPublicArray(),
            'avatar'    => $avatar,
            'wallet'    => $wallet,
            'stats'     => [
                'videos'    => (int) $stats->videos,
                'views'     => (int) $stats->views,
                'followers' => $followers,
                'following' => $following,
            ],
        ]);
    }

    public function profile(Request $request): Response
    {
        $username = $request->routeParam('username');
        $user     = User::findByUsername($username);

        if (!$user || $user->status === 'banned') {
            return Response::error('User not found.', 404);
        }

        $avatar    = Database::fetchOne("SELECT * FROM avatars WHERE user_id = ?", [$user->id]);
        $followers = (int) Database::fetchOne("SELECT COUNT(*) AS c FROM follows WHERE following_id = ?", [$user->id])->c;
        $following = (int) Database::fetchOne("SELECT COUNT(*) AS c FROM follows WHERE follower_id = ?", [$user->id])->c;
        $videos    = (int) Database::fetchOne("SELECT COUNT(*) AS c FROM videos WHERE author_id = ? AND status = 'public'", [$user->id])->c;
        $likes     = (int) Database::fetchOne("SELECT SUM(like_count) AS c FROM videos WHERE author_id = ?", [$user->id])->c;

        $viewerId   = $request->user()?->id;
        $isFollowing = $viewerId ? (bool) Database::fetchOne(
            "SELECT 1 FROM follows WHERE follower_id = ? AND following_id = ?",
            [$viewerId, $user->id]
        ) : false;

        return Response::ok([
            'user'         => $user->toPublicArray(),
            'avatar'       => $avatar,
            'is_following' => $isFollowing,
            'stats'        => compact('followers', 'following', 'videos', 'likes'),
        ]);
    }

    public function updateProfile(Request $request): Response
    {
        $user = $request->user();
        $data = $request->only(['display_name', 'bio', 'country']);

        if (!empty($data['bio']) && strlen($data['bio']) > 500) {
            return Response::error('Bio cannot exceed 500 characters.', 422);
        }

        if (!empty($data)) {
            Database::update('users', $data, ['id' => $user->id]);
        }

        return Response::ok(User::findById($user->id)->toPublicArray(), 'Profile updated.');
    }

    public function changePassword(Request $request): Response
    {
        $user = $request->user();
        $data = $request->validate([
            'current_password' => 'required',
            'new_password'     => 'required|min:8|max:128',
        ]);

        $dbUser = Database::fetchOne("SELECT password_hash FROM users WHERE id = ?", [$user->id]);

        if (!password_verify($data['current_password'], $dbUser->password_hash)) {
            return Response::error('Current password is incorrect.', 401);
        }

        Database::update('users', [
            'password_hash' => password_hash($data['new_password'], PASSWORD_ARGON2ID),
        ], ['id' => $user->id]);

        return Response::ok(null, 'Password changed successfully.');
    }

    public function deleteAccount(Request $request): Response
    {
        $user = $request->user();
        $data = $request->validate(['password' => 'required']);

        $dbUser = Database::fetchOne("SELECT password_hash FROM users WHERE id = ?", [$user->id]);

        if (!password_verify($data['password'], $dbUser->password_hash)) {
            return Response::error('Password is incorrect.', 401);
        }

        // Soft delete: anonymize rather than purge
        Database::update('users', [
            'status'       => 'deactivated',
            'email'        => "deleted_{$user->id}@avatartok.invalid",
            'username'     => "deleted_{$user->id}",
            'display_name' => 'Deleted User',
            'bio'          => null,
        ], ['id' => $user->id]);

        return Response::ok(null, 'Account deleted.');
    }

    public function search(Request $request): Response
    {
        $q     = $request->query('q', '');
        $page  = max((int) $request->query('page', 1), 1);
        $limit = min((int) $request->query('limit', 20), 50);

        if (strlen($q) < 2) {
            return Response::error('Search query too short.', 422);
        }

        $like  = "%{$q}%";
        $total = (int) Database::fetchOne(
            "SELECT COUNT(*) AS c FROM users WHERE (username LIKE ? OR display_name LIKE ?) AND status = 'active'",
            [$like, $like]
        )->c;

        $users = Database::fetchAll(
            "SELECT u.id, u.username, u.display_name, av.avatar_url,
                    (SELECT COUNT(*) FROM follows WHERE following_id = u.id) AS followers
             FROM users u LEFT JOIN avatars av ON av.user_id = u.id
             WHERE (u.username LIKE ? OR u.display_name LIKE ?) AND u.status = 'active'
             ORDER BY followers DESC LIMIT ? OFFSET ?",
            [$like, $like, $limit, ($page - 1) * $limit]
        );

        return Response::paginated($users, $total, $page, $limit);
    }

    public function suggested(Request $request): Response
    {
        $userId = $request->user()->id;

        $users = Database::fetchAll(
            "SELECT u.id, u.username, u.display_name, av.avatar_url,
                    (SELECT COUNT(*) FROM follows WHERE following_id = u.id) AS followers
             FROM users u LEFT JOIN avatars av ON av.user_id = u.id
             WHERE u.id != ?
               AND u.status = 'active'
               AND u.id NOT IN (SELECT following_id FROM follows WHERE follower_id = ?)
               AND u.id NOT IN (SELECT blocked_id FROM blocks WHERE blocker_id = ?)
             ORDER BY followers DESC LIMIT 20",
            [$userId, $userId, $userId]
        );

        return Response::ok($users);
    }
}
