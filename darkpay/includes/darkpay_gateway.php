<?php
// Shared DarkPay production gateway helpers.
require_once __DIR__ . '/../../store/includes/currency.php';
require_once __DIR__ . '/../../store/includes/notifications.php';

function darkpayEnv($key, $default = '')
{
    $value = getenv($key);
    return ($value === false || $value === '') ? $default : $value;
}

function darkpaySetting(PDO $pdo, $key, $default = '')
{
    return currencySetting($pdo, 'darkpay_' . $key, $default);
}

function darkpayConfig(PDO $pdo, $key, $envKey, $default = '')
{
    $env = darkpayEnv($envKey);
    return $env !== '' ? $env : darkpaySetting($pdo, $key, $default);
}

function darkpaySaveSettings(PDO $pdo, $merchantName, $merchantVpa, $webhookSecret, $gatewayMode)
{
    darkpayEnsureSchema($pdo);

    $merchantName = trim($merchantName);
    $merchantVpa = trim($merchantVpa);
    $gatewayMode = in_array($gatewayMode, ['test', 'live'], true) ? $gatewayMode : 'test';

    if ($merchantName === '') {
        throw new Exception('Merchant name is required.');
    }

    if (!preg_match('/^[a-zA-Z0-9.\-_]{2,256}@[a-zA-Z]{2,64}$/', $merchantVpa)) {
        throw new Exception('Enter a valid UPI VPA, for example merchant@bank.');
    }

    currencySaveSetting($pdo, 'darkpay_merchant_name', $merchantName);
    currencySaveSetting($pdo, 'darkpay_upi_vpa', $merchantVpa);
    currencySaveSetting($pdo, 'darkpay_gateway_mode', $gatewayMode);

    if (trim($webhookSecret) !== '') {
        currencySaveSetting($pdo, 'darkpay_webhook_secret', trim($webhookSecret));
    }
}

function darkpayJson($data, $statusCode = 200)
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

function darkpayColumnExists(PDO $pdo, $table, $column)
{
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
    $stmt->execute([$column]);
    return (bool) $stmt->fetch();
}

function darkpayEnsureSchema(PDO $pdo)
{
    currencyEnsureSettings($pdo);
    notificationEnsureSchema($pdo);
    $defaults = [
        'darkpay_merchant_name' => 'Dark Gaming Store',
        'darkpay_upi_vpa' => 'merchant@upi',
        'darkpay_webhook_secret' => '',
        'darkpay_gateway_mode' => 'test',
    ];

    foreach ($defaults as $key => $value) {
        if (currencySetting($pdo, $key, null) === null) {
            currencySaveSetting($pdo, $key, $value);
        }
    }

    if (!darkpayColumnExists($pdo, 'orders', 'payment_method')) {
        $pdo->exec("ALTER TABLE `orders` ADD COLUMN `payment_method` VARCHAR(50) DEFAULT 'credit_card' AFTER `status`");
    }

    if (!darkpayColumnExists($pdo, 'orders', 'txid')) {
        $pdo->exec("ALTER TABLE `orders` ADD COLUMN `txid` VARCHAR(100) DEFAULT NULL AFTER `payment_method`");
        $pdo->exec("ALTER TABLE `orders` ADD UNIQUE INDEX unique_txid (`txid`)");
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS wallet_deposits (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        amount DECIMAL(10, 2) NOT NULL,
        payment_method VARCHAR(50) NOT NULL,
        txid VARCHAR(100) DEFAULT NULL UNIQUE,
        status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");

    $orderColumns = [
        'customer_upi_ref' => "ALTER TABLE `orders` ADD COLUMN `customer_upi_ref` VARCHAR(100) DEFAULT NULL AFTER `txid`",
        'provider_txid' => "ALTER TABLE `orders` ADD COLUMN `provider_txid` VARCHAR(100) DEFAULT NULL AFTER `customer_upi_ref`",
        'paid_amount' => "ALTER TABLE `orders` ADD COLUMN `paid_amount` DECIMAL(10, 2) DEFAULT NULL AFTER `provider_txid`",
        'verified_at' => "ALTER TABLE `orders` ADD COLUMN `verified_at` TIMESTAMP NULL DEFAULT NULL AFTER `paid_amount`",
        'provider_payload' => "ALTER TABLE `orders` ADD COLUMN `provider_payload` TEXT DEFAULT NULL AFTER `verified_at`",
    ];

    foreach ($orderColumns as $column => $sql) {
        if (!darkpayColumnExists($pdo, 'orders', $column)) {
            $pdo->exec($sql);
        }
    }

    $depositColumns = [
        'customer_upi_ref' => "ALTER TABLE `wallet_deposits` ADD COLUMN `customer_upi_ref` VARCHAR(100) DEFAULT NULL AFTER `txid`",
        'provider_txid' => "ALTER TABLE `wallet_deposits` ADD COLUMN `provider_txid` VARCHAR(100) DEFAULT NULL AFTER `customer_upi_ref`",
        'paid_amount' => "ALTER TABLE `wallet_deposits` ADD COLUMN `paid_amount` DECIMAL(10, 2) DEFAULT NULL AFTER `provider_txid`",
        'verified_at' => "ALTER TABLE `wallet_deposits` ADD COLUMN `verified_at` TIMESTAMP NULL DEFAULT NULL AFTER `paid_amount`",
        'provider_payload' => "ALTER TABLE `wallet_deposits` ADD COLUMN `provider_payload` TEXT DEFAULT NULL AFTER `verified_at`",
    ];

    foreach ($depositColumns as $column => $sql) {
        if (!darkpayColumnExists($pdo, 'wallet_deposits', $column)) {
            $pdo->exec($sql);
        }
    }
}

function darkpayExpectedAmount(PDO $pdo, $reference, $type)
{
    if ($type === 'deposit') {
        $stmt = $pdo->prepare("SELECT amount FROM wallet_deposits WHERE txid = ? LIMIT 1");
        $stmt->execute([$reference]);
        $amount = $stmt->fetchColumn();
        return $amount === false ? null : currencyToDarkPayInr($pdo, (float) $amount);
    }

    $stmt = $pdo->prepare("SELECT SUM(c.price) FROM orders o JOIN cards c ON o.card_id = c.id WHERE o.txid = ? OR o.txid LIKE ?");
    $stmt->execute([$reference, $reference . '-%']);
    $amount = $stmt->fetchColumn();
    return $amount === false ? null : currencyToDarkPayInr($pdo, (float) $amount);
}

function darkpayStatus(PDO $pdo, $reference, $type)
{
    if ($type === 'deposit') {
        $stmt = $pdo->prepare("SELECT status, provider_txid, customer_upi_ref FROM wallet_deposits WHERE txid = ? LIMIT 1");
        $stmt->execute([$reference]);
        return $stmt->fetch();
    }

    $stmt = $pdo->prepare("SELECT status, provider_txid, customer_upi_ref FROM orders WHERE txid = ? OR txid LIKE ? LIMIT 1");
    $stmt->execute([$reference, $reference . '-%']);
    return $stmt->fetch();
}

function darkpayRecordCustomerReference(PDO $pdo, $reference, $type, $customerUpiRef)
{
    if ($customerUpiRef === '') {
        return;
    }

    if ($type === 'deposit') {
        $stmt = $pdo->prepare("UPDATE wallet_deposits SET customer_upi_ref = ? WHERE txid = ? AND status = 'pending'");
        $stmt->execute([$customerUpiRef, $reference]);
        return;
    }

    $stmt = $pdo->prepare("UPDATE orders SET customer_upi_ref = ? WHERE (txid = ? OR txid LIKE ?) AND status = 'pending'");
    $stmt->execute([$customerUpiRef, $reference, $reference . '-%']);
}

function darkpayMarkVerified(PDO $pdo, $reference, $type, $providerTxid, $paidAmount, $payload)
{
    darkpayEnsureSchema($pdo);
    $expected = darkpayExpectedAmount($pdo, $reference, $type);

    if ($expected === null || $expected <= 0) {
        throw new Exception('Payment reference was not found.');
    }

    if (abs((float) $paidAmount - $expected) > 0.01) {
        throw new Exception('Paid amount does not match the order amount.');
    }

    $payloadJson = is_string($payload) ? $payload : json_encode($payload);

    if ($type === 'deposit') {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT id, user_id, amount, status FROM wallet_deposits WHERE txid = ? FOR UPDATE");
        $stmt->execute([$reference]);
        $deposit = $stmt->fetch();

        if (!$deposit) {
            $pdo->rollBack();
            throw new Exception('Deposit reference was not found.');
        }

        if ($deposit['status'] === 'verified') {
            $pdo->commit();
            return 'already_verified';
        }

        if ($deposit['status'] !== 'pending') {
            $pdo->rollBack();
            throw new Exception('Only pending deposits can be verified.');
        }

        $stmtUpdate = $pdo->prepare("UPDATE wallet_deposits SET status = 'verified', provider_txid = ?, paid_amount = ?, verified_at = NOW(), provider_payload = ? WHERE id = ?");
        $stmtUpdate->execute([$providerTxid, $paidAmount, $payloadJson, $deposit['id']]);

        $stmtUser = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
        $stmtUser->execute([(float) $deposit['amount'], $deposit['user_id']]);

        notificationCreate(
            $pdo,
            $deposit['user_id'],
            'payment_confirmed',
            'Deposit confirmed',
            'Your DarkPay wallet deposit of INR ' . number_format((float) $paidAmount, 2) . ' has been verified and credited.',
            'dashboard.php'
        );

        $pdo->commit();
        return 'verified';
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT id, user_id, status FROM orders WHERE txid = ? OR txid LIKE ? FOR UPDATE");
    $stmt->execute([$reference, $reference . '-%']);
    $orders = $stmt->fetchAll();

    if (!$orders) {
        $pdo->rollBack();
        throw new Exception('Order reference was not found.');
    }

    $pendingIds = [];
    foreach ($orders as $order) {
        if ($order['status'] === 'pending') {
            $pendingIds[] = (int) $order['id'];
        } elseif ($order['status'] !== 'verified') {
            $pdo->rollBack();
            throw new Exception('Rejected orders cannot be verified.');
        }
    }

    if (!$pendingIds) {
        $pdo->commit();
        return 'already_verified';
    }

    $placeholders = implode(',', array_fill(0, count($pendingIds), '?'));
    $params = array_merge(['verified', $providerTxid, $paidAmount, $payloadJson], $pendingIds);
    $stmtUpdate = $pdo->prepare("UPDATE orders SET status = ?, provider_txid = ?, paid_amount = ?, verified_at = NOW(), provider_payload = ? WHERE id IN ($placeholders)");
    $stmtUpdate->execute($params);

    $notifiedUsers = [];
    foreach ($orders as $order) {
        if (in_array((int) $order['id'], $pendingIds, true)) {
            $notifiedUsers[(int) $order['user_id']] = true;
        }
    }

    foreach (array_keys($notifiedUsers) as $userId) {
        notificationCreate(
            $pdo,
            $userId,
            'order_confirmed',
            'Order confirmed',
            'Your DarkPay payment of INR ' . number_format((float) $paidAmount, 2) . ' was verified. Your order is ready.',
            'dashboard.php'
        );
    }

    $pdo->commit();
    return 'verified';
}

function darkpayVerifyWebhookSignature($rawBody)
{
    global $pdo;
    $secret = isset($pdo) ? darkpayConfig($pdo, 'webhook_secret', 'DARKPAY_WEBHOOK_SECRET', '') : darkpayEnv('DARKPAY_WEBHOOK_SECRET');
    if ($secret === '') {
        return false;
    }

    $signature = $_SERVER['HTTP_X_DARKPAY_SIGNATURE'] ?? $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';
    if ($signature === '') {
        return false;
    }

    $expected = hash_hmac('sha256', $rawBody, $secret);
    return hash_equals($expected, $signature);
}
?>
