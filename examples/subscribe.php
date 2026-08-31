<?php
/**
 * Example: start a recurring monthly subscription for a logged-in user of
 * YOUR OWN site (a SaaS app, a membership site — anything with recurring
 * billing). Call this from your own "Subscribe to Pro" button.
 *
 * subscription_id is set to your own internal user id — this is the field
 * that lets subscription-webhook.php later figure out *which of your
 * users* a webhook event is about. Use whatever identifier makes sense on
 * your side (user id, a "user_id:plan_id" composite, etc.) — Qistass Pay
 * just stores and echoes it back verbatim.
 */

require __DIR__ . '/../src/QistassPay.php';
require __DIR__ . '/../src/QistassPayException.php';
require __DIR__ . '/../src/QistassPayNetworkException.php';

use QistassPay\QistassPay;
use QistassPay\QistassPayException;
use QistassPay\QistassPayNetworkException;

$qistass = new QistassPay(
    getenv('QISTASS_PUBLIC_KEY'),
    getenv('QISTASS_SECRET_KEY'),
    getenv('QISTASS_MERCHANT_NUMBER'),
    getenv('QISTASS_WEBHOOK_SECRET')
);

// In a real app this comes from your own session/auth, not a hardcoded value.
$currentUserId = 'user_42';
$monthlyPrice = 9990; // in your settlement currency, e.g. SYP

try {
    $result = $qistass->createSubscription(
        $monthlyPrice,
        $currentUserId,
        'https://yoursite.com/qistass/subscription-webhook.php',
        'https://yoursite.com/account?subscribed=1'
    );

    // Send the customer to authorize (redirect, or open in the popup
    // pattern from assets/qistass-button.js if you'd rather they stay on
    // your page). Do NOT unlock anything yet — that only happens once
    // subscription-webhook.php receives a real subscription.charged event.
    header('Location: ' . $result['redirect_url']);
} catch (QistassPayException $e) {
    if ($e->status === 'duplicate_subscription_id') {
        // They already have a subscription (active or awaiting
        // authorization) — send them to manage it instead of creating a
        // second one. $e->response['subscription_id'] is the existing one.
        http_response_code(409);
        exit('You already have a subscription in progress.');
    }
    http_response_code(422);
    exit('Could not start subscription: ' . $e->status);
} catch (QistassPayNetworkException $e) {
    http_response_code(502);
    exit('Network error, please try again.');
}
