<?php

declare(strict_types=1);

namespace AvatarTok\Controllers\Admin;

use AvatarTok\Core\Database;
use AvatarTok\Core\Request;
use AvatarTok\Core\Response;
class AdminController
{
    public function root(Request $_request): Response
    {
        return Response::ok([
            'name'    => 'AvatarTok API',
            'version' => '1.0.0',
            'docs'    => 'https://docs.avatartok.com',
            'status'  => 'online',
        ]);
    }

    public function health(Request $request): Response
    {
        $dbOk = false;
        try {
            Database::fetchOne("SELECT 1");
            $dbOk = true;
        } catch (\Throwable) {}

        $status = $dbOk ? 200 : 503;

        return new Response([
            'status'    => $dbOk ? 'ok' : 'degraded',
            'db'        => $dbOk ? 'ok' : 'down',
            'timestamp' => date('c'),
            'uptime_ms' => round((microtime(true) - APP_START) * 1000, 2),
        ], $status);
    }

    public function listUsers(Request $request): Response
    {
        $page    = max((int) $request->query('page', 1), 1);
        $limit   = min((int) $request->query('limit', 50), 200);
        $status  = $request->query('status');
        $search  = $request->query('q');

        $where  = 'WHERE 1=1';
        $params = [];

        if ($status) {
            $where   .= ' AND status = ?';
            $params[] = $status;
        }
        if ($search) {
            $where   .= ' AND (username LIKE ? OR email LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        $total = (int) Database::fetchOne("SELECT COUNT(*) AS c FROM users {$where}", $params)->c;

        $users = Database::fetchAll(
            "SELECT id, username, email, role, status, trust_score, created_at, last_login_at
             FROM users {$where} ORDER BY created_at DESC LIMIT ? OFFSET ?",
            [...$params, $limit, ($page - 1) * $limit]
        );

        return Response::paginated($users, $total, $page, $limit);
    }

    public function userDetail(Request $request): Response
    {
        $userId = $request->routeParam('userId');

        $user = Database::fetchOne(
            "SELECT u.*, w.balance, w.pending_balance
             FROM users u LEFT JOIN wallets w ON w.user_id = u.id
             WHERE u.id = ?",
            [$userId]
        );

        if (!$user) {
            return Response::error('User not found.', 404);
        }

        $stats = Database::fetchOne(
            "SELECT COUNT(*) AS videos, SUM(view_count) AS views FROM videos WHERE author_id = ?",
            [$userId]
        );

        return Response::ok([
            'user'  => $user,
            'stats' => $stats,
        ]);
    }

    public function getPlatformConfig(Request $_request): Response
    {
        $config = Database::fetchAll("SELECT key, value FROM platform_config");
        $result = [];
        foreach ($config as $row) {
            $result[$row->key] = $row->value;
        }
        return Response::ok($result);
    }

    public function updatePlatformConfig(Request $request): Response
    {
        $data = $request->body();

        foreach ($data as $key => $value) {
            Database::query(
                "INSERT INTO platform_config (key, value) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE value = VALUES(value)",
                [$key, $value]
            );
        }

        return Response::ok(null, 'Configuration updated.');
    }

    public function giftCatalog(Request $_request): Response
    {
        $gifts = Database::fetchAll("SELECT * FROM gifts ORDER BY coin_cost ASC");
        return Response::ok($gifts);
    }

    public function addGift(Request $request): Response
    {
        $data = $request->validate([
            'name'          => 'required|max:100',
            'coin_cost'     => 'required|numeric',
            'animation_key' => 'required',
            'rarity'        => 'required',
        ]);

        Database::insert('gifts', [
            'id'            => \Ramsey\Uuid\Uuid::uuid4()->toString(),
            'name'          => $data['name'],
            'coin_cost'     => (int) $data['coin_cost'],
            'animation_key' => $data['animation_key'],
            'rarity'        => $data['rarity'],
            'active'        => 1,
        ]);

        return Response::created(null, 'Gift added to catalog.');
    }

    public function updateGift(Request $request): Response
    {
        $giftId = $request->routeParam('giftId');
        $data   = $request->only(['name', 'coin_cost', 'rarity', 'active']);

        Database::update('gifts', $data, ['id' => $giftId]);
        return Response::ok(null, 'Gift updated.');
    }

    public function listPayoutRequests(Request $request): Response
    {
        $status = $request->query('status', 'pending');
        $page   = max((int) $request->query('page', 1), 1);
        $limit  = min((int) $request->query('limit', 50), 200);

        $total = (int) Database::fetchOne(
            "SELECT COUNT(*) AS c FROM payout_requests WHERE status = ?",
            [$status]
        )->c;

        $payouts = Database::fetchAll(
            "SELECT pr.*, u.username, u.email
             FROM payout_requests pr JOIN users u ON u.id = pr.user_id
             WHERE pr.status = ? ORDER BY pr.created_at ASC LIMIT ? OFFSET ?",
            [$status, $limit, ($page - 1) * $limit]
        );

        return Response::paginated($payouts, $total, $page, $limit);
    }

    public function approvePayout(Request $request): Response
    {
        $id = $request->routeParam('id');
        Database::update('payout_requests', ['status' => 'approved'], ['id' => $id]);
        return Response::ok(null, 'Payout approved.');
    }

    public function rejectPayout(Request $request): Response
    {
        $id   = $request->routeParam('id');
        $data = $request->validate(['reason' => 'required']);
        Database::update('payout_requests', [
            'status'          => 'rejected',
            'rejected_reason' => $data['reason'],
        ], ['id' => $id]);
        return Response::ok(null, 'Payout rejected.');
    }
}
