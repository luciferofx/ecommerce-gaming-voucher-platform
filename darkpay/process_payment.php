<?php
// DarkPay customer confirmation handler.
require_once '../store/includes/db.php';
require_once 'includes/darkpay_gateway.php';

$txid = $_POST['txid'] ?? '';
$amount = (float) ($_POST['amount'] ?? 0.00);
$type = $_POST['type'] ?? 'checkout';
$paymentMethod = $_POST['payment_method'] ?? 'upi';
$customerUpiRef = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $_POST['customer_upi_ref'] ?? ''));

if ($txid === '' || $amount <= 0.00) {
    die("<div style='background-color:#1e1e24;color:#ff6b6b;font-family:sans-serif;padding:30px;text-align:center;border-radius:8px;border:1px solid #ff6b6b;margin:50px auto;max-width:500px;'><h3>Invalid Processing Request</h3><p>Payment reference and valid amount are required.</p><a href='../store/dashboard.php' style='color:#fff;'>Return to Store</a></div>");
}

try {
    darkpayEnsureSchema($pdo);

    darkpayRecordCustomerReference($pdo, $txid, $type, $customerUpiRef);
    $status = darkpayStatus($pdo, $txid, $type);

    if ($status && $status['status'] === 'verified') {
        if ($type === 'deposit') {
            $_SESSION['deposit_success'] = 'Payment verified and wallet credited.';
            header('Location: ../store/dashboard.php');
            exit();
        }

        $_SESSION['success_txid'] = $txid;
        header('Location: ../store/success.php?multiple=true');
        exit();
    }

    if ($type === 'deposit') {
        $_SESSION['deposit_success'] = 'UPI reference submitted. Your wallet will be credited automatically as soon as the payment provider confirms it.';
        header('Location: ../store/dashboard.php');
        exit();
    }

    $_SESSION['success_txid'] = $txid;
    header('Location: ../store/success.php?multiple=true');
    exit();
} catch (Exception $e) {
    $_SESSION['deposit_error'] = 'DarkPay payment check failed: ' . $e->getMessage();
    header('Location: ../store/dashboard.php');
    exit();
}
?>
