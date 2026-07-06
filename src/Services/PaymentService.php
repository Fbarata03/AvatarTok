<?php

declare(strict_types=1);

namespace AvatarTok\Services;

use AvatarTok\Core\Database;
use Ramsey\Uuid\Uuid;
use Stripe\StripeClient;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;

class PaymentService
{
    private StripeClient $stripe;

    // Coin package catalog (coins => USD cents)
    private const COIN_PACKAGES = [
        'pack_100'   => ['coins' => 100,   'price_cents' => 99,    'label' => '100 Coins'],
        'pack_500'   => ['coins' => 500,   'price_cents' => 449,   'label' => '500 Coins'],
        'pack_1000'  => ['coins' => 1000,  'price_cents' => 849,   'label' => '1,000 Coins'],
        'pack_5000'  => ['coins' => 5000,  'price_cents' => 3999,  'label' => '5,000 Coins'],
        'pack_10000' => ['coins' => 10000, 'price_cents' => 7499,  'label' => '10,000 Coins'],
    ];

    // Platform cut on gift transactions: 30%
    private const PLATFORM_CUT = 0.30;

    public function __construct()
    {
        $this->stripe = new StripeClient($_ENV['STRIPE_SECRET_KEY']);
    }

    public function getCoinPackage(string $packageId): ?array
    {
        return self::COIN_PACKAGES[$packageId] ?? null;
    }

    public function getOrCreateStripeCustomer(string $userId): string
    {
        $user = Database::fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);

        if ($user->stripe_customer_id) {
            return $user->stripe_customer_id;
        }

        $customer = $this->stripe->customers->create([
            'email'    => $user->email,
            'metadata' => ['user_id' => $userId],
        ]);

        Database::update('users', ['stripe_customer_id' => $customer->id], ['id' => $userId]);

        return $customer->id;
    }

    public function createStripeIntent(string $userId, array $package, string $currency): array
    {
        $customerId = $this->getOrCreateStripeCustomer($userId);
        $txId       = Uuid::uuid4()->toString();

        $intent = $this->stripe->paymentIntents->create([
            'amount'   => $package['price_cents'],
            'currency' => strtolower($currency),
            'customer' => $customerId,
            'metadata' => [
                'user_id'    => $userId,
                'package_id' => array_search($package, self::COIN_PACKAGES),
                'coins'      => $package['coins'],
                'tx_id'      => $txId,
            ],
            'automatic_payment_methods' => ['enabled' => true],
        ]);

        // Record pending transaction
        Database::insert('transactions', [
            'id'                => $txId,
            'user_id'           => $userId,
            'type'              => 'coin_purchase',
            'amount_cents'      => $package['price_cents'],
            'currency'          => strtolower($currency),
            'coins'             => $package['coins'],
            'stripe_intent_id'  => $intent->id,
            'status'            => 'pending',
            'created_at'        => date('Y-m-d H:i:s'),
        ]);

        return [
            'id'            => $intent->id,
            'client_secret' => $intent->client_secret,
            'amount'        => $intent->amount,
            'currency'      => $intent->currency,
        ];
    }

    public function verifyStripeWebhook(string $payload, string $signature): mixed
    {
        try {
            return Webhook::constructEvent($payload, $signature, $_ENV['STRIPE_WEBHOOK_SECRET']);
        } catch (SignatureVerificationException) {
            return null;
        }
    }

    public function handleStripeEvent(object $event): void
    {
        match($event->type) {
            'payment_intent.succeeded'  => $this->onPaymentSucceeded($event->data->object),
            'payment_intent.payment_failed' => $this->onPaymentFailed($event->data->object),
            'customer.subscription.created' => $this->onSubscriptionCreated($event->data->object),
            'customer.subscription.deleted' => $this->onSubscriptionDeleted($event->data->object),
            'payout.paid'               => $this->onPayoutPaid($event->data->object),
            default                     => null,
        };
    }

    private function onPaymentSucceeded(object $intent): void
    {
        $txId  = $intent->metadata['tx_id']  ?? null;
        $coins = (int) ($intent->metadata['coins'] ?? 0);

        if (!$txId || !$coins) {
            return;
        }

        Database::beginTransaction();
        try {
            Database::update('transactions', ['status' => 'completed', 'completed_at' => date('Y-m-d H:i:s')], ['id' => $txId]);
            Database::query("UPDATE wallets SET balance = balance + ? WHERE user_id = ?", [$coins, $intent->metadata['user_id']]);
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }
    }

    private function onPaymentFailed(object $intent): void
    {
        $txId = $intent->metadata['tx_id'] ?? null;
        if ($txId) {
            Database::update('transactions', ['status' => 'failed'], ['id' => $txId]);
        }
    }

    public function attachPaymentMethod(string $userId, string $stripeMethodId): object
    {
        $customerId = $this->getOrCreateStripeCustomer($userId);

        $this->stripe->paymentMethods->attach($stripeMethodId, ['customer' => $customerId]);

        $method = $this->stripe->paymentMethods->retrieve($stripeMethodId);

        $id = Uuid::uuid4()->toString();
        Database::insert('payment_methods', [
            'id'                      => $id,
            'user_id'                 => $userId,
            'stripe_payment_method_id'=> $stripeMethodId,
            'type'                    => $method->type,
            'last4'                   => $method->card->last4   ?? null,
            'brand'                   => $method->card->brand   ?? null,
            'exp_month'               => $method->card->exp_month ?? null,
            'exp_year'                => $method->card->exp_year  ?? null,
            'created_at'              => date('Y-m-d H:i:s'),
        ]);

        return Database::fetchOne("SELECT * FROM payment_methods WHERE id = ?", [$id]);
    }

    public function detachPaymentMethod(string $userId, string $methodId): bool
    {
        $method = Database::fetchOne(
            "SELECT * FROM payment_methods WHERE id = ? AND user_id = ?",
            [$methodId, $userId]
        );

        if (!$method) {
            return false;
        }

        $this->stripe->paymentMethods->detach($method->stripe_payment_method_id);
        Database::query("DELETE FROM payment_methods WHERE id = ?", [$methodId]);

        return true;
    }

    public function listPaymentMethods(string $userId): array
    {
        return Database::fetchAll(
            "SELECT id, type, last4, brand, exp_month, exp_year, created_at FROM payment_methods WHERE user_id = ?",
            [$userId]
        );
    }

    public function getHistory(string $userId, int $page, int $limit, ?string $type): array
    {
        $where  = "WHERE user_id = ?";
        $params = [$userId];

        if ($type) {
            $where   .= " AND type = ?";
            $params[] = $type;
        }

        $total = (int) Database::fetchOne("SELECT COUNT(*) AS c FROM transactions {$where}", $params)->c;

        $rows = Database::fetchAll(
            "SELECT * FROM transactions {$where} ORDER BY created_at DESC LIMIT ? OFFSET ?",
            [...$params, $limit, ($page - 1) * $limit]
        );

        return ['transactions' => $rows, 'total' => $total];
    }

    public function findTransaction(string $txId, string $userId): mixed
    {
        return Database::fetchOne(
            "SELECT * FROM transactions WHERE id = ? AND user_id = ?",
            [$txId, $userId]
        );
    }

    public function createSubscription(string $userId, string $planId, string $paymentMethodId): object
    {
        $customerId = $this->getOrCreateStripeCustomer($userId);

        $this->stripe->customers->update($customerId, [
            'invoice_settings' => ['default_payment_method' => $paymentMethodId],
        ]);

        $plan = $this->getSubscriptionPlan($planId);

        $sub = $this->stripe->subscriptions->create([
            'customer' => $customerId,
            'items'    => [['price' => $plan['stripe_price_id']]],
            'metadata' => ['user_id' => $userId, 'plan_id' => $planId],
        ]);

        $id = Uuid::uuid4()->toString();
        Database::insert('subscriptions', [
            'id'                    => $id,
            'user_id'               => $userId,
            'plan_id'               => $planId,
            'stripe_subscription_id'=> $sub->id,
            'status'                => $sub->status,
            'current_period_end'    => date('Y-m-d H:i:s', $sub->current_period_end),
            'created_at'            => date('Y-m-d H:i:s'),
        ]);

        return Database::fetchOne("SELECT * FROM subscriptions WHERE id = ?", [$id]);
    }

    public function cancelSubscription(string $userId): void
    {
        $sub = Database::fetchOne(
            "SELECT * FROM subscriptions WHERE user_id = ? AND status = 'active'",
            [$userId]
        );

        if (!$sub) {
            return;
        }

        // Cancel at period end (not immediately)
        $this->stripe->subscriptions->update($sub->stripe_subscription_id, [
            'cancel_at_period_end' => true,
        ]);

        Database::update('subscriptions', ['cancel_at_period_end' => 1], ['id' => $sub->id]);
    }

    private function onSubscriptionCreated(object $sub): void
    {
        Database::update('subscriptions',
            ['status' => $sub->status],
            ['stripe_subscription_id' => $sub->id]
        );
    }

    private function onSubscriptionDeleted(object $sub): void
    {
        Database::update('subscriptions',
            ['status' => 'cancelled', 'cancelled_at' => date('Y-m-d H:i:s')],
            ['stripe_subscription_id' => $sub->id]
        );
    }

    private function onPayoutPaid(object $payout): void
    {
        Database::update('payout_requests',
            ['status' => 'paid', 'paid_at' => date('Y-m-d H:i:s')],
            ['stripe_payout_id' => $payout->id]
        );
    }

    private function getSubscriptionPlan(string $planId): array
    {
        $plans = [
            'creator_pro' => ['stripe_price_id' => 'price_creator_pro', 'amount_cents' => 999],
            'studio'      => ['stripe_price_id' => 'price_studio',       'amount_cents' => 2999],
        ];

        if (!isset($plans[$planId])) {
            throw new \InvalidArgumentException("Unknown plan: {$planId}");
        }

        return $plans[$planId];
    }
}
