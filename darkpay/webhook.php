<?php
require_once '../store/includes/db.php';
require_once 'includes/darkpay_gateway.php';

$rawBody = file_get_contents('php://input');

if (!darkpayVerifyWebhookSignature($rawBody)) {
    darkpayJson(['ok' => false, 'error' => 'Invalid webhook signature.'], 401);
}

$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    darkpayJson(['ok' => false, 'error' => 'Invalid JSON payload.'], 400);
}

$event = $payload['event'] ?? '';
$payment = $payload['payment'] ?? $payload['payload']['payment']['entity'] ?? $payload;
$status = strtolower((string) ($payment['status'] ?? $payment['captured'] ?? ''));
$isPaid = in_array($status, ['success', 'paid', 'captured', 'authorized', '1'], true);

if (!$isPaid && !in_array($event, ['payment.captured', 'payment.authorized'], true)) {
    darkpayJson(['ok' => true, 'ignored' => true, 'reason' => 'Payment is not successful yet.']);
}

$reference = $payment['reference'] ?? $payment['merchant_order_id'] ?? $payment['notes']['darkpay_reference'] ?? $payload['reference'] ?? '';
$type = $payment['type'] ?? $payment['notes']['darkpay_type'] ?? $payload['type'] ?? 'checkout';
$providerTxid = $payment['upi_transaction_id'] ?? $payment['provider_txid'] ?? $payment['id'] ?? $payload['provider_txid'] ?? '';
$amount = $payment['amount_decimal']
    ?? $payment['amount_major']
    ?? $payload['amount_decimal']
    ?? $payment['amount']
    ?? $payload['amount']
    ?? 0;

if (isset($payment['amount_minor'])) {
    $amount = (float) $payment['amount_minor'] / 100;
}

if ($reference === '' || $providerTxid === '' || (float) $amount <= 0) {
    darkpayJson(['ok' => false, 'error' => 'Webhook is missing reference, provider transaction id, or amount.'], 422);
}

try {
    $expectedAmount = darkpayExpectedAmount($pdo, $reference, $type);
    if ($expectedAmount !== null && abs((float) $amount - $expectedAmount) > 0.01 && abs(((float) $amount / 100) - $expectedAmount) <= 0.01) {
        $amount = (float) $amount / 100;
    }

    $result = darkpayMarkVerified($pdo, $reference, $type, $providerTxid, (float) $amount, $payload);
    darkpayJson(['ok' => true, 'result' => $result]);
} catch (Exception $e) {
    darkpayJson(['ok' => false, 'error' => $e->getMessage()], 422);
}
?>
