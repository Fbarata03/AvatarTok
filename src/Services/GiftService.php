<?php

declare(strict_types=1);

namespace AvatarTok\Services;

use AvatarTok\Core\Database;
use Ramsey\Uuid\Uuid;

class GiftService
{
    // Platform takes 30% of gift value; creator receives 70%
    private const PLATFORM_CUT_PCT = 0.30;

    public function sendLiveGift(string $senderId, string $streamId, string $giftId, int $quantity): array
    {
        $gift   = $this->findGift($giftId);
        $stream = Database::fetchOne("SELECT * FROM live_streams WHERE id = ?", [$streamId]);

        if (!$gift || !$stream || $stream->status !== 'live') {
            return ['success' => false, 'message' => 'Stream not active or gift not found.'];
        }

        $totalCoins = $gift->coin_cost * $quantity;

        // Atomic wallet deduction
        $affected = Database::query(
            "UPDATE wallets SET balance = balance - ? WHERE user_id = ? AND balance >= ?",
            [$totalCoins, $senderId, $totalCoins]
        )->rowCount();

        if (!$affected) {
            return ['success' => false, 'message' => 'Insufficient coins.'];
        }

        $txId = Uuid::uuid4()->toString();

        Database::beginTransaction();
        try {
            Database::insert('gift_transactions', [
                'id'           => $txId,
                'sender_id'    => $senderId,
                'receiver_id'  => $stream->user_id,
                'stream_id'    => $streamId,
                'gift_id'      => $giftId,
                'quantity'     => $quantity,
                'coins_total'  => $totalCoins,
                'platform_cut' => (int) round($totalCoins * self::PLATFORM_CUT_PCT),
                'creator_cut'  => (int) round($totalCoins * (1 - self::PLATFORM_CUT_PCT)),
                'created_at'   => date('Y-m-d H:i:s'),
            ]);

            // Credit creator's pending balance (held until payout)
            $creatorCut = (int) round($totalCoins * (1 - self::PLATFORM_CUT_PCT));
            Database::query(
                "UPDATE wallets SET pending_balance = pending_balance + ? WHERE user_id = ?",
                [$creatorCut, $stream->user_id]
            );

            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            // Refund sender
            Database::query(
                "UPDATE wallets SET balance = balance + ? WHERE user_id = ?",
                [$totalCoins, $senderId]
            );
            throw $e;
        }

        // Publish gift event to live stream's pub/sub channel
        (new CacheService())->publish("stream:{$streamId}:gifts", [
            'type'        => 'gift',
            'gift_id'     => $giftId,
            'gift_name'   => $gift->name,
            'animation'   => $gift->animation_key,
            'quantity'    => $quantity,
            'sender_id'   => $senderId,
            'coins_total' => $totalCoins,
            'timestamp'   => time(),
        ]);

        $wallet = Database::fetchOne("SELECT balance FROM wallets WHERE user_id = ?", [$senderId]);

        return [
            'success'       => true,
            'animation_key' => $gift->animation_key,
            'coins_spent'   => $totalCoins,
            'new_balance'   => (int) $wallet->balance,
        ];
    }

    public function listGiftCatalog(): array
    {
        return Database::fetchAll(
            "SELECT * FROM gifts WHERE active = 1 ORDER BY coin_cost ASC"
        );
    }

    private function findGift(string $giftId): mixed
    {
        return Database::fetchOne("SELECT * FROM gifts WHERE id = ? AND active = 1", [$giftId]);
    }
}
