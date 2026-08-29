<?php
/**
 * Example endpoint the drop-in JS button POSTs to (see index.html in this
 * folder). Runs on YOUR server, creates the order with Qistass Pay using
 * your secret key (never exposed to the browser), and returns
 * { redirect_url } for the button to navigate to.
 *
 * Copy this pattern into your own site's backend (any framework — this is
 * plain PHP so it works everywhere, including no framework at all).
 */

require __DIR__ . '/../src/QistassPay.php';
require __DIR__ . '/../src/QistassPayException.php';
require __DIR__ . '/../src/QistassPayNetworkException.php';

use QistassPay\QistassPay;
use QistassPay\QistassPayException;
use QistassPay\QistassPayNetworkException;

header('Content-Type: application/json');

// --- Your real keys go here (env vars, not hardcoded — shown here only for clarity) ---
$qistass = new QistassPay(
    getenv('QISTASS_PUBLIC_KEY'),
    getenv('QISTASS_SECRET_KEY'),
    getenv('QISTASS_MERCHANT_NUMBER'),
    getenv('QISTASS_WEBHOOK_SECRET')
);

// In a real store this would come from your own cart/order system, not the
// client request — never trust an amount sent from the browser.
$order = [
    'id' => 'ORD-' . date('Ymd-His') . '-' . random_int(100, 999),
    'amount' => 45000, // SYP — look this up from your own order record
];

try {
    $result = $qistass->createPaymentOrder(
        $order['amount'],
        $order['id'],
        'https://yoursite.com/qistass/webhook.php',
        'https://yoursite.com/qistass/return.php?order_id=' . urlencode($order['id'])
    );

    // Persist $order['id'] => $result['transaction_id'] in your own DB here,
    // so return.php / webhook.php can look the order back up.

    echo json_encode(['redirect_url' => $result['redirect_url']]);
} catch (QistassPayException $e) {
    http_response_code(422);
    echo json_encode(['error' => $e->status]);
} catch (QistassPayNetworkException $e) {
    http_response_code(502);
    echo json_encode(['error' => 'network_error']);
}
