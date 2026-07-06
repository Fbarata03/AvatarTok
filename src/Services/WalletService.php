<?php

declare(strict_types=1);

namespace AvatarTok\Services;

use AvatarTok\Core\Database;
use Ramsey\Uuid\Uuid;

class WalletService
{
    // Minimum payout: 10,000 coins (~$70 USD)
    private const MIN_PAYOUT_COINS = 10_000;

    // Exchange rate: 100 coins = $0.70 USD (platform keeps ~30% spread)
    private const COINS_PER_USD = 142;

    private const PAYOUT_FEE_PCT = 0.01; // 1% processing fee

    public function requestPayout(string $userId, int $coins, string $method, string $accountId): array
    {
        if ($coins < self::MIN_PAYOUT_COINS) {
            return [
                'success' => false,
                'message' => sprintf('Minimum payout is %s coins.', number_format(self::MIN_PAYOUT_COINS)),
            ];
        }

        $wallet = Database::fetchOne("SELECT * FROM wallets WHERE user_id = ?", [$userId]);

        if (!$wallet || $wallet->pending_balance < $coins) {
            return ['success' => false, 'message' => 'Insufficient pending balance.'];
        }

        $amountUsdCents = (int) round(($coins / self::COINS_PER_USD) * 100);
        $feeCents       = (int) round($amountUsdCents * self::PAYOUT_FEE_PCT);

        Database::beginTransaction();
        try {
            // Deduct from pending balance
            Database::query(
                "UPDATE wallets SET pending_balance = pending_balance - ? WHERE user_id = ?",
                [$coins, $userId]
            );

            $payoutId = Uuid::uuid4()->toString();
            Database::insert('payout_requests', [
                'id'              => $payoutId,
                'user_id'         => $userId,
                'amount_coins'    => $coins,
                'amount_usd_cents'=> $amountUsdCents,
                'fee_usd_cents'   => $feeCents,
                'method'          => $method,
                'account_id'      => $accountId,
                'status'          => 'pending',
                'estimated_arrival' => date('Y-m-d', strtotime('+3 business days')),
                'created_at'      => date('Y-m-d H:i:s'),
            ]);

            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }

        return [
            'success'           => true,
            'payout_id'         => $payoutId,
            'amount_usd'        => round($amountUsdCents / 100, 2),
            'fee_usd'           => round($feeCents / 100, 2),
            'estimated_arrival' => date('Y-m-d', strtotime('+3 business days')),
        ];
    }

    public function getPayoutStatus(string $payoutId, string $userId): mixed
    {
        return Database::fetchOne(
            "SELECT * FROM payout_requests WHERE id = ? AND user_id = ?",
            [$payoutId, $userId]
        );
    }

    public function getBalance(string $userId): array
    {
        $wallet = Database::fetchOne(
            "SELECT balance, pending_balance, total_earned, total_spent FROM wallets WHERE user_id = ?",
            [$userId]
        );

        return [
            'spendable_coins'    => (int) ($wallet->balance          ?? 0),
            'pending_coins'      => (int) ($wallet->pending_balance  ?? 0),
            'total_earned_coins' => (int) ($wallet->total_earned     ?? 0),
            'total_spent_coins'  => (int) ($wallet->total_spent      ?? 0),
            'exchange_rate'      => ['coins_per_usd' => self::COINS_PER_USD],
        ];
    }
}
