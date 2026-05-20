<?php
require_once '../store/includes/db.php';
require_once 'includes/darkpay_gateway.php';

$txid = $_GET['txid'] ?? '';
$type = $_GET['type'] ?? 'checkout';

if ($txid === '') {
    darkpayJson(['ok' => false, 'error' => 'Missing payment reference.'], 400);
}

try {
    darkpayEnsureSchema($pdo);
    $status = darkpayStatus($pdo, $txid, $type);

    if (!$status) {
        darkpayJson(['ok' => false, 'error' => 'Payment reference not found.'], 404);
    }

    darkpayJson([
        'ok' => true,
        'status' => $status['status'],
        'provider_txid' => $status['provider_txid'] ?? null,
        'customer_upi_ref' => $status['customer_upi_ref'] ?? null,
    ]);
} catch (Exception $e) {
    darkpayJson(['ok' => false, 'error' => $e->getMessage()], 500);
}
?>
