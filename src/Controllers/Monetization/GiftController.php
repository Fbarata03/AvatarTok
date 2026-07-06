<?php

declare(strict_types=1);

namespace AvatarTok\Controllers\Monetization;

use AvatarTok\Core\Request;
use AvatarTok\Core\Response;
use AvatarTok\Services\GiftService;
use AvatarTok\Services\WalletService;

class GiftController
{
    public function __construct(
        private readonly GiftService  $gifts  = new GiftService(),
        private readonly WalletService $wallet = new WalletService()
    ) {}

    public function listGiftCatalog(Request $request): Response
    {
        return Response::ok($this->gifts->listGiftCatalog());
    }

    public function sendGift(Request $request): Response
    {
        $data = $request->validate([
            'gift_id'    => 'required',
            'quantity'   => 'required|numeric',
            'target_id'  => 'required',
            'target_type'=> 'required', // 'live_stream' | 'video' | 'user'
        ]);

        if ($data['target_type'] === 'live_stream') {
            $result = $this->gifts->sendLiveGift(
                $request->user()->id,
                $data['target_id'],
                $data['gift_id'],
                (int) $data['quantity']
            );
        } else {
            return Response::error("Gift target type '{$data['target_type']}' not yet supported.", 422);
        }

        if (!$result['success']) {
            return Response::error($result['message'], 402);
        }

        return Response::ok($result);
    }

    public function receivedGifts(Request $request): Response
    {
        $page   = max((int) $request->query('page', 1), 1);
        $limit  = min((int) $request->query('limit', 20), 100);
        $userId = $request->user()->id;

        $total = (int) \AvatarTok\Core\Database::fetchOne(
            "SELECT COUNT(*) AS c FROM gift_transactions WHERE receiver_id = ?", [$userId]
        )->c;

        $gifts = \AvatarTok\Core\Database::fetchAll(
            "SELECT gt.*, g.name AS gift_name, g.icon_url, u.username AS sender_username, av.avatar_url AS sender_avatar
             FROM gift_transactions gt
             JOIN gifts g ON g.id = gt.gift_id
             JOIN users u ON u.id = gt.sender_id
             LEFT JOIN avatars av ON av.user_id = u.id
             WHERE gt.receiver_id = ? ORDER BY gt.created_at DESC LIMIT ? OFFSET ?",
            [$userId, $limit, ($page - 1) * $limit]
        );

        return Response::paginated($gifts, $total, $page, $limit);
    }

    public function sentGifts(Request $request): Response
    {
        $page   = max((int) $request->query('page', 1), 1);
        $limit  = min((int) $request->query('limit', 20), 100);
        $userId = $request->user()->id;

        $total = (int) \AvatarTok\Core\Database::fetchOne(
            "SELECT COUNT(*) AS c FROM gift_transactions WHERE sender_id = ?", [$userId]
        )->c;

        $gifts = \AvatarTok\Core\Database::fetchAll(
            "SELECT gt.*, g.name AS gift_name, g.icon_url, u.username AS receiver_username
             FROM gift_transactions gt
             JOIN gifts g ON g.id = gt.gift_id
             JOIN users u ON u.id = gt.receiver_id
             WHERE gt.sender_id = ? ORDER BY gt.created_at DESC LIMIT ? OFFSET ?",
            [$userId, $limit, ($page - 1) * $limit]
        );

        return Response::paginated($gifts, $total, $page, $limit);
    }

    public function wallet(Request $request): Response
    {
        return Response::ok($this->wallet->getBalance($request->user()->id));
    }

    public function topUpWallet(Request $request): Response
    {
        if (($_ENV['APP_ENV'] ?? 'production') === 'local') {
            $data = $request->validate([
                'coins' => 'required|numeric'
            ]);
            $userId = $request->user()->id;
            $this->wallet->creditWallet($userId, (int) $data['coins'], 'top_up', 'Simulação de recarga local');
            return Response::ok($this->wallet->getBalance($userId), 'Carteira recarregada.');
        }
        // Delegated to PaymentController::createPaymentIntent
        return Response::error('Use /api/v1/payments/stripe/intent to top up.', 303);
    }

    public function withdrawEarnings(Request $request): Response
    {
        $data = $request->validate([
            'amount_coins' => 'required|numeric',
            'method'       => 'required',
            'account_id'   => 'required',
        ]);

        $result = $this->wallet->requestPayout(
            $request->user()->id,
            (int) $data['amount_coins'],
            $data['method'],
            $data['account_id']
        );

        if (!$result['success']) {
            return Response::error($result['message'], 422);
        }

        return Response::ok($result, 'Withdrawal requested.');
    }

    public function giftLeaderboard(Request $request): Response
    {
        $type   = $request->query('type', 'receiver'); // receiver | sender
        $period = $request->query('period', '7d');
        $limit  = min((int) $request->query('limit', 20), 50);

        $col    = $type === 'sender' ? 'sender_id' : 'receiver_id';
        $since  = match($period) {
            '24h'  => date('Y-m-d H:i:s', strtotime('-24 hours')),
            '7d'   => date('Y-m-d H:i:s', strtotime('-7 days')),
            '30d'  => date('Y-m-d H:i:s', strtotime('-30 days')),
            default=> date('Y-m-d H:i:s', strtotime('-7 days')),
        };

        $board = \AvatarTok\Core\Database::fetchAll(
            "SELECT u.id, u.username, av.avatar_url, SUM(gt.coins_total) AS total_coins
             FROM gift_transactions gt
             JOIN users u ON u.id = gt.{$col}
             LEFT JOIN avatars av ON av.user_id = u.id
             WHERE gt.created_at >= ?
             GROUP BY u.id ORDER BY total_coins DESC LIMIT {$limit}",
            [$since]
        );

        return Response::ok($board);
    }
}
