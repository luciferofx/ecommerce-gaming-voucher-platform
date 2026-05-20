<?php
require_once '../store/includes/db.php';
require_once 'includes/darkpay_gateway.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../store/login.php');
    exit();
}

$action = $_GET['action'] ?? '';

try {
    darkpayEnsureSchema($pdo);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method.');
    }

    if ($action === 'save_settings') {
        darkpaySaveSettings(
            $pdo,
            $_POST['merchant_name'] ?? '',
            $_POST['upi_vpa'] ?? '',
            $_POST['webhook_secret'] ?? '',
            $_POST['gateway_mode'] ?? 'test'
        );
        $_SESSION['darkpay_success'] = 'DarkPay settings saved.';
    } elseif ($action === 'verify_checkout') {
        $reference = trim($_POST['reference'] ?? '');
        $providerTxid = trim($_POST['provider_txid'] ?? '');
        if ($providerTxid === '') {
            $providerTxid = 'ADMIN-' . strtoupper(bin2hex(random_bytes(6)));
        }
        $amount = darkpayExpectedAmount($pdo, $reference, 'checkout');
        darkpayMarkVerified($pdo, $reference, 'checkout', $providerTxid, $amount, ['source' => 'darkpay_admin', 'admin_id' => $_SESSION['user_id']]);
        $_SESSION['darkpay_success'] = 'Checkout payment verified.';
    } elseif ($action === 'verify_deposit') {
        $reference = trim($_POST['reference'] ?? '');
        $providerTxid = trim($_POST['provider_txid'] ?? '');
        if ($providerTxid === '') {
            $providerTxid = 'ADMIN-' . strtoupper(bin2hex(random_bytes(6)));
        }
        $amount = darkpayExpectedAmount($pdo, $reference, 'deposit');
        darkpayMarkVerified($pdo, $reference, 'deposit', $providerTxid, $amount, ['source' => 'darkpay_admin', 'admin_id' => $_SESSION['user_id']]);
        $_SESSION['darkpay_success'] = 'Wallet deposit verified and credited.';
    } elseif ($action === 'reject_checkout') {
        $reference = trim($_POST['reference'] ?? '');
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT o.id, o.user_id, o.card_id, o.status, c.game_name, c.card_value FROM orders o JOIN cards c ON c.id = o.card_id WHERE o.txid = ? OR o.txid LIKE ? FOR UPDATE");
        $stmt->execute([$reference, $reference . '-%']);
        $orders = $stmt->fetchAll();
        if (!$orders) {
            throw new Exception('Checkout reference not found.');
        }
        foreach ($orders as $order) {
            if ($order['status'] !== 'pending') {
                continue;
            }
            $stmtOrder = $pdo->prepare("UPDATE orders SET status = 'rejected' WHERE id = ?");
            $stmtOrder->execute([$order['id']]);
            $stmtCard = $pdo->prepare("UPDATE cards SET status = 'available' WHERE id = ?");
            $stmtCard->execute([$order['card_id']]);
            notificationCreate($pdo, $order['user_id'], 'order_rejected', 'Order rejected', 'Your DarkPay order for ' . $order['game_name'] . ' ' . $order['card_value'] . ' was rejected.', 'dashboard.php');
        }
        $pdo->commit();
        $_SESSION['darkpay_success'] = 'Checkout payment rejected and stock restored.';
    } elseif ($action === 'reject_deposit') {
        $reference = trim($_POST['reference'] ?? '');
        $pdo->beginTransaction();
        $stmtDep = $pdo->prepare("SELECT user_id, amount, status FROM wallet_deposits WHERE txid = ? FOR UPDATE");
        $stmtDep->execute([$reference]);
        $deposit = $stmtDep->fetch();
        if (!$deposit) {
            throw new Exception('Deposit reference not found.');
        }
        if ($deposit['status'] !== 'pending') {
            throw new Exception('Only pending deposits can be rejected.');
        }
        $stmt = $pdo->prepare("UPDATE wallet_deposits SET status = 'rejected' WHERE txid = ?");
        $stmt->execute([$reference]);
        notificationCreate($pdo, $deposit['user_id'], 'payment_rejected', 'Deposit rejected', 'Your DarkPay wallet deposit of ' . currencyFormat($pdo, $deposit['amount'], 'USD') . ' was rejected.', 'dashboard.php');
        $pdo->commit();
        $_SESSION['darkpay_success'] = 'Wallet deposit rejected.';
    } else {
        throw new Exception('Unknown DarkPay action.');
    }
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['darkpay_error'] = $e->getMessage();
}

header('Location: admin.php');
exit();
?>
