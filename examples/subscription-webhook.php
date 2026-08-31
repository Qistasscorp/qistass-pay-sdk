<?php
/**
 * Example webhook receiver for subscription lifecycle events. Set this URL
 * as the webhook_url passed to createSubscription() (see subscribe.php).
 *
 * This is the ONLY place that should ever unlock or lock a paid feature.
 * The customer's browser redirect back to your callback_url is for UX only
 * (showing a "you're subscribed!" message) — it must never be what flips
 * the unlock flag itself, since a browser redirect can be closed, delayed,
 * or (if you built the check client-side) spoofed. This handler runs
 * server-to-server and is signature-verified, so it's the trustworthy one.
 *
 * Events you'll receive here:
 *   subscription.charged        — first authorization AND every successful
 *                                  monthly renewal (same event both times).
 *   subscription.payment_failed — a renewal failed (insufficient balance).
 *                                  Subscription is still alive, will retry
 *                                  automatically in 2 days.
 *   subscription.canceled       — no further charges will ever happen.
 *                                  Fired whether the merchant, the customer,
 *                                  or 2 consecutive failed renewals caused it
 *                                  — treat it the same way regardless of cause.
 */

require __DIR__ . '/../src/QistassPay.php';
require __DIR__ . '/../src/QistassPayException.php';

use QistassPay\QistassPay;
use QistassPay\QistassPayException;

$qistass = new QistassPay(
    getenv('QISTASS_PUBLIC_KEY'),
    getenv('QISTASS_SECRET_KEY'),
    getenv('QISTASS_MERCHANT_NUMBER'),
    getenv('QISTASS_WEBHOOK_SECRET')
);

try {
    $payload = $qistass->handleIncomingWebhook(); // verifies X-Qistass-Signature internally
} catch (QistassPayException $e) {
    http_response_code(403);
    exit('Invalid signature');
}

$event = $payload['event'] ?? null;
// This is the same value you passed as subscription_id to createSubscription()
// — your own user id, in this example.
$userId = $payload['merchant_subscription_id'] ?? null;

if (!$event || !$userId) {
    http_response_code(400);
    exit('Missing event/merchant_subscription_id');
}

// Make every branch idempotent — Qistass Pay (like any webhook sender) may
// retry delivery, so the same event can arrive more than once.
switch ($event) {
    case 'subscription.charged':
        // UPDATE users SET plan = 'pro' WHERE id = $userId;
        break;

    case 'subscription.payment_failed':
        // Optional: show a "please top up your wallet" banner next time
        // they log in. Do NOT downgrade them yet — a payment_failed event
        // means Qistass Pay will retry automatically in 2 days; only
        // subscription.canceled means it's really over.
        // UPDATE users SET billing_notice = 'payment_failed' WHERE id = $userId;
        break;

    case 'subscription.canceled':
        // UPDATE users SET plan = 'free' WHERE id = $userId;
        break;
}

http_response_code(200);
echo 'ok';
