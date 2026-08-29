<?php
/**
 * Example webhook receiver. Set this URL as `webhook_url` when creating an
 * order (see create-checkout.php). Qistass Pay POSTs here the moment a
 * payment completes, signed with X-Qistass-Signature (HMAC-SHA256).
 *
 * Per the API docs: never trust the webhook body alone to fulfil an order —
 * always re-verify with payment-verification (isPaid()) before acting.
 */

require __DIR__ . '/../src/QistassPay.php';
require __DIR__ . '/../src/QistassPayException.php';
require __DIR__ . '/../src/QistassPayNetworkException.php';

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

$orderId = $payload['order_id'] ?? null;
$transactionId = $payload['transaction_id'] ?? null;
$claimedAmount = $payload['amount'] ?? null;

if (!$orderId || !$transactionId) {
    http_response_code(400);
    exit('Missing order_id/transaction_id');
}

// Look up your own order record by $orderId here to know the amount you
// actually expect — never trust $claimedAmount from the webhook body.
$expectedAmount = null; // = YourOrders::find($orderId)->amount;

if ($qistass->isPaid($transactionId, $expectedAmount)) {
    // Mark $orderId as paid in your own system, trigger fulfilment, etc.
    // Make this idempotent — Qistass Pay (like any webhook sender) may
    // retry delivery, so the same transaction_id can arrive more than once.
}

http_response_code(200);
echo 'ok';
