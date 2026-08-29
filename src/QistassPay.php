<?php

namespace QistassPay;

/**
 * Qistass Pay — Server-Side PHP SDK
 *
 * Thin wrapper around the live REST API documented at
 * https://pay.qistass.com/developers/ — talks to /api/v1/create-payment-order,
 * /api/v1/payment-verification, and verifies inbound webhook signatures.
 *
 * IMPORTANT: this class must only ever run on your server. secret_key is sent
 * as part of the request body to Qistass Pay (per their documented API) and
 * must never be embedded in front-end/browser code.
 *
 * Usage:
 *   $qistass = new QistassPay($publicKey, $secretKey, $merchantNumber, $webhookSecret);
 *   $order = $qistass->createPaymentOrder(45000, 'ORD-2026-0142',
 *       'https://yoursite.com/webhook/qistass', 'https://yoursite.com/payment/return');
 *   header('Location: ' . $order['redirect_url']);
 */
class QistassPay
{
    private const BASE_URL = 'https://pay.qistass.com';

    private string $publicKey;
    private string $secretKey;
    private string $merchantNumber;
    private ?string $webhookSecret;
    private int $timeoutSeconds;

    public function __construct(
        string $publicKey,
        string $secretKey,
        string $merchantNumber,
        ?string $webhookSecret = null,
        int $timeoutSeconds = 20
    ) {
        $this->publicKey = $publicKey;
        $this->secretKey = $secretKey;
        $this->merchantNumber = $merchantNumber;
        $this->webhookSecret = $webhookSecret;
        $this->timeoutSeconds = $timeoutSeconds;
    }

    /**
     * Create a new payment order and get back the URL to redirect the
     * customer to in order to complete payment.
     *
     * @param float  $amount      Order amount, in the merchant's own configured
     *                            settlement currency (set on their Qistass Pay
     *                            dashboard) — NOT always SYP. Confirmed live: a
     *                            SAR-settling merchant's checkout page displayed
     *                            an amount passed here as ر.س, not ل.س, despite
     *                            the public API docs literally saying "(ل.س)".
     * @param string $orderId     A unique order identifier from your own system.
     * @param string|null $webhookUrl  Where Qistass Pay should POST payment notifications.
     * @param string|null $callbackUrl Where the customer is redirected back to after paying.
     *
     * @return array{status:string, redirect_url:string, transaction_id:string}
     * @throws QistassPayException on any non-success response (e.g. merchant_not_found).
     * @throws QistassPayNetworkException on a transport-level failure.
     */
    public function createPaymentOrder(
        float $amount,
        string $orderId,
        ?string $webhookUrl = null,
        ?string $callbackUrl = null
    ): array {
        $payload = [
            'public_key' => $this->publicKey,
            'secret_key' => $this->secretKey,
            'merchant_number' => $this->merchantNumber,
            'amount' => $amount,
            'order_id' => $orderId,
        ];

        if ($webhookUrl !== null) {
            $payload['webhook_url'] = $webhookUrl;
        }
        if ($callbackUrl !== null) {
            $payload['callback_url'] = $callbackUrl;
        }

        $response = $this->post('/api/v1/create-payment-order', $payload);

        if (($response['status'] ?? null) !== 'payment_created' || empty($response['redirect_url'])) {
            throw new QistassPayException(
                $response['status'] ?? 'unknown_error',
                $response['message'] ?? 'Qistass Pay did not return a redirect_url.',
                $response
            );
        }

        return $response;
    }

    /**
     * Verify a payment's real status directly with Qistass Pay. Always call
     * this before fulfilling an order — never trust the callback redirect or
     * the webhook alone (both can be spoofed or replayed).
     *
     * @return array{is_paid?:int, amount?:float, transaction_id?:string} Empty array if not found.
     */
    public function verifyPayment(string $transactionId): array
    {
        $payload = [
            'public_key' => $this->publicKey,
            'secret_key' => $this->secretKey,
            'merchant_number' => $this->merchantNumber,
            'transaction_id' => $transactionId,
        ];

        $response = $this->post('/api/v1/payment-verification', $payload);

        return $response['payment_record'] ?? [];
    }

    /**
     * Convenience wrapper around verifyPayment(): returns true only if the
     * transaction is genuinely paid AND (when you pass an expected amount)
     * the paid amount matches exactly — guards against a tampered amount on
     * your own side.
     */
    public function isPaid(string $transactionId, ?float $expectedAmount = null): bool
    {
        $record = $this->verifyPayment($transactionId);

        if ((int) ($record['is_paid'] ?? 0) !== 1) {
            return false;
        }

        if ($expectedAmount !== null && isset($record['amount'])) {
            // Guard against float rounding noise on the wire.
            if (abs((float) $record['amount'] - $expectedAmount) > 0.01) {
                return false;
            }
        }

        return true;
    }

    /**
     * Verify the X-Qistass-Signature header on an inbound webhook request.
     * Pass the RAW, unparsed request body — signing is computed over the
     * exact bytes Qistass Pay sent, not a re-serialized version of it.
     *
     * Requires $webhookSecret to have been passed to the constructor.
     */
    public function verifyWebhookSignature(string $rawBody, ?string $signatureHeader): bool
    {
        if ($this->webhookSecret === null || $this->webhookSecret === '') {
            throw new \RuntimeException(
                'QistassPay: webhookSecret was not configured — cannot verify webhook signatures.'
            );
        }

        if ($signatureHeader === null || $signatureHeader === '') {
            return false;
        }

        $calculated = hash_hmac('sha256', $rawBody, $this->webhookSecret);

        return hash_equals($calculated, $signatureHeader);
    }

    /**
     * Parse + verify a webhook request in one call. Reads the raw body and
     * the signature header directly from PHP's own superglobals, so this
     * only works when called from within the actual HTTP request that
     * received the webhook (not from a queued/replayed context).
     *
     * @return array The decoded webhook payload (order_id, transaction_id, status, amount).
     * @throws QistassPayException if the signature is missing or invalid.
     */
    public function handleIncomingWebhook(): array
    {
        $rawBody = file_get_contents('php://input');
        $signature = $_SERVER['HTTP_X_QISTASS_SIGNATURE'] ?? null;

        if (!$this->verifyWebhookSignature((string) $rawBody, $signature)) {
            throw new QistassPayException('invalid_signature', 'Webhook signature verification failed.', []);
        }

        $decoded = json_decode((string) $rawBody, true);

        if (!is_array($decoded)) {
            throw new QistassPayException('invalid_payload', 'Webhook body was not valid JSON.', []);
        }

        return $decoded;
    }

    private function post(string $path, array $payload): array
    {
        $ch = curl_init(self::BASE_URL . $path);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $raw = curl_exec($ch);

        if ($raw === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new QistassPayNetworkException("Qistass Pay request failed: {$error}");
        }

        curl_close($ch);

        $decoded = json_decode((string) $raw, true);

        if (!is_array($decoded)) {
            throw new QistassPayNetworkException(
                'Qistass Pay returned a non-JSON response: ' . substr((string) $raw, 0, 300)
            );
        }

        return $decoded;
    }
}
