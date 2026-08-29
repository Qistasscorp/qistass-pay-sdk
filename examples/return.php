<?php
/**
 * Example callback page. Set this URL as `callback_url` when creating an
 * order — the customer's browser lands here after paying. Per the docs,
 * always re-verify with payment-verification before showing "success" —
 * never trust the redirect alone (a customer could reload/forge the URL).
 */

require __DIR__ . '/../src/QistassPay.php';
require __DIR__ . '/../src/QistassPayException.php';
require __DIR__ . '/../src/QistassPayNetworkException.php';

use QistassPay\QistassPay;

$qistass = new QistassPay(
    getenv('QISTASS_PUBLIC_KEY'),
    getenv('QISTASS_SECRET_KEY'),
    getenv('QISTASS_MERCHANT_NUMBER')
);

$orderId = $_GET['order_id'] ?? null;
$transactionId = $_GET['transaction_id'] ?? null;

// Look up the transaction_id you stored against $orderId in create-checkout.php
// if you didn't rely on Qistass Pay to echo it back in the query string.

$expectedAmount = null; // = YourOrders::find($orderId)->amount;
$paid = $transactionId ? $qistass->isPaid($transactionId, $expectedAmount) : false;

if ($paid) {
    echo 'تم الدفع بنجاح، شكراً لك.';
} else {
    echo 'لم يتم تأكيد الدفع بعد. إذا خُصم المبلغ، تواصل معنا.';
}
