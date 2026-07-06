<?php

declare(strict_types=1);

namespace AvatarTok\Controllers\Monetization;

use AvatarTok\Core\Request;
use AvatarTok\Core\Response;
use AvatarTok\Services\PaymentService;
use AvatarTok\Services\WalletService;

class PaymentController
{
    public function __construct(
        private readonly PaymentService $payments = new PaymentService(),
        private readonly WalletService  $wallet   = new WalletService()
    ) {}

    public function listMethods(Request $request): Response
    {
        $methods = $this->payments->listPaymentMethods($request->user()->id);
        return Response::ok($methods);
    }

    public function addMethod(Request $request): Response
    {
        $data = $request->validate(['stripe_payment_method_id' => 'required']);

        $method = $this->payments->attachPaymentMethod(
            $request->user()->id,
            $data['stripe_payment_method_id']
        );

        return Response::created($method, 'Payment method added.');
    }

    public function removeMethod(Request $request): Response
    {
        $methodId = $request->routeParam('methodId');
        $success  = $this->payments->detachPaymentMethod($request->user()->id, $methodId);

        if (!$success) {
            return Response::error('Payment method not found.', 404);
        }

        return Response::noContent();
    }

    public function history(Request $request): Response
    {
        $page  = max((int) $request->query('page', 1), 1);
        $limit = min((int) $request->query('limit', 20), 100);
        $type  = $request->query('type');

        $result = $this->payments->getHistory($request->user()->id, $page, $limit, $type);
        return Response::paginated($result['transactions'], $result['total'], $page, $limit);
    }

    public function transactionDetail(Request $request): Response
    {
        $txId = $request->routeParam('txId');
        $tx   = $this->payments->findTransaction($txId, $request->user()->id);

        if (!$tx) {
            return Response::error('Transaction not found.', 404);
        }

        return Response::ok($tx);
    }

    /**
     * Creates a Stripe PaymentIntent for coin wallet top-up.
     * The client confirms the payment directly with Stripe (no card data touches our server).
     */
    public function createPaymentIntent(Request $request): Response
    {
        $data = $request->validate([
            'package_id' => 'required', // maps to a predefined coin package
            'currency'   => 'required|max:3',
        ]);

        $package = $this->payments->getCoinPackage($data['package_id']);
        if (!$package) {
            return Response::error('Invalid coin package.', 422);
        }

        $intent = $this->payments->createStripeIntent(
            $request->user()->id,
            $package,
            $data['currency']
        );

        return Response::ok([
            'client_secret'  => $intent['client_secret'],
            'payment_intent' => $intent['id'],
            'amount'         => $intent['amount'],
            'currency'       => $intent['currency'],
            'coins_to_credit'=> $package['coins'],
        ]);
    }

    /**
     * Stripe webhook — verified via signature, not auth token.
     */
    public function stripeWebhook(Request $request): Response
    {
        $payload   = file_get_contents('php://input');
        $sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

        $event = $this->payments->verifyStripeWebhook($payload, $sigHeader);

        if (!$event) {
            return Response::error('Invalid webhook signature.', 400);
        }

        $this->payments->handleStripeEvent($event);

        return Response::ok(null, 'Webhook received.');
    }

    public function requestPayout(Request $request): Response
    {
        $user = $request->user();
        $data = $request->validate([
            'amount_coins' => 'required|numeric',
            'method'       => 'required', // 'bank_transfer' | 'paypal' | 'stripe_express'
            'account_id'   => 'required',
        ]);

        $result = $this->wallet->requestPayout(
            $user->id,
            (int) $data['amount_coins'],
            $data['method'],
            $data['account_id']
        );

        if (!$result['success']) {
            return Response::error($result['message'], 422);
        }

        return Response::ok([
            'payout_id'        => $result['payout_id'],
            'estimated_arrival'=> $result['estimated_arrival'],
            'amount_usd'       => $result['amount_usd'],
            'fee_usd'          => $result['fee_usd'],
        ], 'Payout request submitted.');
    }

    public function payoutStatus(Request $request): Response
    {
        $payoutId = $request->query('payout_id');
        $status   = $this->wallet->getPayoutStatus($payoutId, $request->user()->id);

        if (!$status) {
            return Response::error('Payout not found.', 404);
        }

        return Response::ok($status);
    }

    public function subscribe(Request $request): Response
    {
        $data = $request->validate([
            'plan_id'            => 'required',
            'payment_method_id'  => 'required',
        ]);

        $sub = $this->payments->createSubscription(
            $request->user()->id,
            $data['plan_id'],
            $data['payment_method_id']
        );

        return Response::created($sub, 'Subscription activated.');
    }

    public function cancelSubscription(Request $request): Response
    {
        $this->payments->cancelSubscription($request->user()->id);
        return Response::ok(null, 'Subscription will cancel at end of billing period.');
    }
}
