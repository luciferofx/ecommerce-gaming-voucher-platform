<?php
require_once '../store/includes/db.php';
require_once '../store/includes/crypto_helper.php';
require_once 'includes/darkpay_gateway.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../store/login.php');
    exit();
}

darkpayEnsureSchema($pdo);

$merchantName = darkpayConfig($pdo, 'merchant_name', 'DARKPAY_MERCHANT_NAME', 'Dark Gaming Store');
$merchantVpa = darkpayConfig($pdo, 'upi_vpa', 'DARKPAY_UPI_VPA', 'merchant@upi');
$webhookSecret = darkpayConfig($pdo, 'webhook_secret', 'http://localhost/dark/darkpay/webhook.php', '');
$gatewayMode = darkpaySetting($pdo, 'gateway_mode', 'live');
$webhookUrl = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . dirname($_SERVER['SCRIPT_NAME']) . '/webhook.php';

$stats = [
    'checkout_pending' => 0,
    'checkout_verified' => 0,
    'deposit_pending' => 0,
    'deposit_verified' => 0,
];
$stats['checkout_pending'] = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE payment_method = 'payment_app' AND status = 'pending'")->fetchColumn();
$stats['checkout_verified'] = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE payment_method = 'payment_app' AND status = 'verified'")->fetchColumn();
$stats['deposit_pending'] = (int) $pdo->query("SELECT COUNT(*) FROM wallet_deposits WHERE payment_method = 'payment_app' AND status = 'pending'")->fetchColumn();
$stats['deposit_verified'] = (int) $pdo->query("SELECT COUNT(*) FROM wallet_deposits WHERE payment_method = 'payment_app' AND status = 'verified'")->fetchColumn();

$stmtOrders = $pdo->query("SELECT SUBSTRING_INDEX(o.txid, '-', 2) AS reference, MIN(o.customer_upi_ref) AS customer_upi_ref,
                                  MIN(o.provider_txid) AS provider_txid, MAX(o.paid_amount) AS paid_amount,
                                  MAX(o.verified_at) AS verified_at, MIN(o.status) AS status, MIN(o.purchase_date) AS purchase_date,
                                  u.username, COUNT(*) AS quantity, SUM(c.price) AS total_usd,
                                  GROUP_CONCAT(CONCAT(c.game_name, ' ', c.card_value) SEPARATOR ', ') AS products
                           FROM orders o
                           JOIN users u ON u.id = o.user_id
                           JOIN cards c ON c.id = o.card_id
                           WHERE o.payment_method = 'payment_app'
                           GROUP BY SUBSTRING_INDEX(o.txid, '-', 2), u.username
                           ORDER BY purchase_date DESC
                           LIMIT 100");
$checkoutPayments = $stmtOrders->fetchAll();

$stmtDeposits = $pdo->query("SELECT d.txid, d.customer_upi_ref, d.provider_txid, d.paid_amount, d.verified_at, d.status, d.created_at,
                                    d.amount, u.username
                             FROM wallet_deposits d
                             JOIN users u ON u.id = d.user_id
                             WHERE d.payment_method = 'payment_app'
                             ORDER BY d.created_at DESC
                             LIMIT 100");
$depositPayments = $stmtDeposits->fetchAll();

require_once '../store/includes/header.php';
?>

<div style="display: flex; justify-content: space-between; gap: 16px; align-items: center; margin-bottom: 25px; flex-wrap: wrap;">
    <div>
        <h1 style="margin: 0; font-weight: 800; background: linear-gradient(90deg, var(--primary-color), var(--secondary-color)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">DarkPay Admin</h1>
        <p style="color: #999; margin-top: 6px;">Gateway settings, UPI verification queue, and provider webhook status.</p>
    </div>
    <a href="../store/admin.php" class="btn btn-secondary" style="color: #000;">Store Admin</a>
</div>

<?php
if (isset($_SESSION['darkpay_success'])) {
    echo "<div class='alert alert-success'>" . htmlspecialchars($_SESSION['darkpay_success']) . "</div>";
    unset($_SESSION['darkpay_success']);
}
if (isset($_SESSION['darkpay_error'])) {
    echo "<div class='alert alert-error'>" . htmlspecialchars($_SESSION['darkpay_error']) . "</div>";
    unset($_SESSION['darkpay_error']);
}
?>

<div class="card-grid" style="grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); margin-bottom: 30px;">
    <div class="glass-container" style="padding: 20px; border-radius: 8px;"><div style="color:#888;">Checkout Pending</div><div style="font-size:2rem;font-weight:800;color:orange;"><?php echo $stats['checkout_pending']; ?></div></div>
    <div class="glass-container" style="padding: 20px; border-radius: 8px;"><div style="color:#888;">Checkout Verified</div><div style="font-size:2rem;font-weight:800;color:var(--secondary-color);"><?php echo $stats['checkout_verified']; ?></div></div>
    <div class="glass-container" style="padding: 20px; border-radius: 8px;"><div style="color:#888;">Deposit Pending</div><div style="font-size:2rem;font-weight:800;color:orange;"><?php echo $stats['deposit_pending']; ?></div></div>
    <div class="glass-container" style="padding: 20px; border-radius: 8px;"><div style="color:#888;">Deposit Verified</div><div style="font-size:2rem;font-weight:800;color:var(--secondary-color);"><?php echo $stats['deposit_verified']; ?></div></div>
</div>

<div class="admin-grid-layout">
    <div class="form-container glass-container" style="margin:0;max-width:100%;border:1px solid rgba(3,218,198,0.2);">
        <h2 style="margin-bottom:18px;color:var(--secondary-color);">Gateway Settings</h2>
        <form action="admin_action.php?action=save_settings" method="POST">
            <div class="form-group">
                <label>Merchant Name</label>
                <input type="text" name="merchant_name" value="<?php echo htmlspecialchars($merchantName); ?>" required>
            </div>
            <div class="form-group">
                <label>UPI VPA</label>
                <input type="text" name="upi_vpa" value="<?php echo htmlspecialchars($merchantVpa); ?>" placeholder="merchant@bank" required>
            </div>
            <div class="form-group">
                <label>Gateway Mode</label>
                <select name="gateway_mode">
                    <option value="test" <?php echo $gatewayMode === 'test' ? 'selected' : ''; ?>>Test</option>
                    <option value="live" <?php echo $gatewayMode === 'live' ? 'selected' : ''; ?>>Live</option>
                </select>
            </div>
            <div class="form-group">
                <label>Webhook Secret</label>
                <input type="password" name="webhook_secret" placeholder="<?php echo $webhookSecret ? 'Saved. Leave blank to keep current secret.' : 'Set webhook signing secret'; ?>">
            </div>
            <button type="submit" class="btn btn-secondary" style="width:100%;color:#000;">Save Gateway</button>
        </form>
    </div>

    <div class="glass-container" style="padding: 25px; border-radius: 8px; border:1px solid rgba(187,134,252,0.18);">
        <h2 style="margin-bottom:18px;color:var(--primary-color);">Provider Webhook</h2>
        <div style="background:#111;padding:12px;border-radius:6px;border:1px solid #333;font-family:monospace;word-break:break-all;color:var(--secondary-color);"><?php echo htmlspecialchars($webhookUrl); ?></div>
        <p style="color:#aaa;margin-top:14px;">Configure this URL in your UPI provider. Header must be <strong>X-DarkPay-Signature</strong> or <strong>X-Razorpay-Signature</strong> using HMAC-SHA256 over raw JSON.</p>
        <div style="margin-top:18px;display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;">
            <div style="background:#151515;padding:14px;border-radius:8px;border:1px solid #333;"><div style="color:#888;">Merchant VPA</div><strong><?php echo htmlspecialchars($merchantVpa); ?></strong></div>
            <div style="background:#151515;padding:14px;border-radius:8px;border:1px solid #333;"><div style="color:#888;">Mode</div><strong><?php echo htmlspecialchars(strtoupper($gatewayMode)); ?></strong></div>
            <div style="background:#151515;padding:14px;border-radius:8px;border:1px solid #333;"><div style="color:#888;">Secret</div><strong><?php echo $webhookSecret ? 'Configured' : 'Missing'; ?></strong></div>
        </div>
    </div>
</div>

<h2 style="margin-top:35px;">DarkPay Checkout Payments</h2>
<div class="table-wrapper">
    <table>
        <thead>
            <tr>
                <th>Reference</th>
                <th>User</th>
                <th>Products</th>
                <th>Amount</th>
                <th>UPI / Provider</th>
                <th>Status</th>
                <th style="text-align:right;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($checkoutPayments as $payment): ?>
                <?php $expectedInr = currencyToDarkPayInr($pdo, $payment['total_usd']); ?>
                <tr>
                    <td style="font-family:monospace;"><?php echo htmlspecialchars($payment['reference']); ?><br><span style="color:#777;font-size:0.8rem;"><?php echo date('M d, H:i', strtotime($payment['purchase_date'])); ?></span></td>
                    <td><?php echo htmlspecialchars($payment['username']); ?></td>
                    <td><?php echo htmlspecialchars($payment['products']); ?><br><span style="color:#888;">Qty: <?php echo (int) $payment['quantity']; ?></span></td>
                    <td><strong>₹<?php echo number_format($expectedInr, 2); ?> INR</strong><br><span style="color:#888;"><?php echo htmlspecialchars(currencyFormat($pdo, $payment['total_usd'], 'USD')); ?></span></td>
                    <td>
                        <span style="color:#aaa;">UTR:</span> <?php echo htmlspecialchars($payment['customer_upi_ref'] ?: '-'); ?><br>
                        <span style="color:#aaa;">Provider:</span> <?php echo htmlspecialchars($payment['provider_txid'] ?: '-'); ?>
                    </td>
                    <td><?php echo htmlspecialchars(ucfirst($payment['status'])); ?></td>
                    <td style="text-align:right;">
                        <?php if ($payment['status'] === 'pending'): ?>
                            <form action="admin_action.php?action=verify_checkout" method="POST" style="display:inline-block;margin:0 0 6px 0;">
                                <input type="hidden" name="reference" value="<?php echo htmlspecialchars($payment['reference']); ?>">
                                <input type="text" name="provider_txid" placeholder="Provider txid" style="width:150px;padding:6px;background:#111;color:#fff;border:1px solid #444;border-radius:4px;">
                                <button class="btn btn-secondary" style="padding:6px 10px;color:#000;">Verify</button>
                            </form>
                            <form action="admin_action.php?action=reject_checkout" method="POST" style="display:inline-block;margin:0;">
                                <input type="hidden" name="reference" value="<?php echo htmlspecialchars($payment['reference']); ?>">
                                <button class="btn" style="padding:6px 10px;background:linear-gradient(135deg,#cf6679,#b00020);color:#fff;">Reject</button>
                            </form>
                        <?php else: ?>
                            <span style="color:#777;">Processed</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$checkoutPayments): ?><tr><td colspan="7" class="text-center">No DarkPay checkout payments yet.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<h2 style="margin-top:35px;">DarkPay Wallet Deposits</h2>
<div class="table-wrapper" style="margin-bottom:40px;">
    <table>
        <thead>
            <tr>
                <th>Reference</th>
                <th>User</th>
                <th>Amount</th>
                <th>UPI / Provider</th>
                <th>Status</th>
                <th style="text-align:right;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($depositPayments as $payment): ?>
                <?php $expectedInr = currencyToDarkPayInr($pdo, $payment['amount']); ?>
                <tr>
                    <td style="font-family:monospace;"><?php echo htmlspecialchars($payment['txid']); ?><br><span style="color:#777;font-size:0.8rem;"><?php echo date('M d, H:i', strtotime($payment['created_at'])); ?></span></td>
                    <td><?php echo htmlspecialchars($payment['username']); ?></td>
                    <td><strong>₹<?php echo number_format($expectedInr, 2); ?> INR</strong><br><span style="color:#888;"><?php echo htmlspecialchars(currencyFormat($pdo, $payment['amount'], 'USD')); ?></span></td>
                    <td>
                        <span style="color:#aaa;">UTR:</span> <?php echo htmlspecialchars($payment['customer_upi_ref'] ?: '-'); ?><br>
                        <span style="color:#aaa;">Provider:</span> <?php echo htmlspecialchars($payment['provider_txid'] ?: '-'); ?>
                    </td>
                    <td><?php echo htmlspecialchars(ucfirst($payment['status'])); ?></td>
                    <td style="text-align:right;">
                        <?php if ($payment['status'] === 'pending'): ?>
                            <form action="admin_action.php?action=verify_deposit" method="POST" style="display:inline-block;margin:0 0 6px 0;">
                                <input type="hidden" name="reference" value="<?php echo htmlspecialchars($payment['txid']); ?>">
                                <input type="text" name="provider_txid" placeholder="Provider txid" style="width:150px;padding:6px;background:#111;color:#fff;border:1px solid #444;border-radius:4px;">
                                <button class="btn btn-secondary" style="padding:6px 10px;color:#000;">Verify</button>
                            </form>
                            <form action="admin_action.php?action=reject_deposit" method="POST" style="display:inline-block;margin:0;">
                                <input type="hidden" name="reference" value="<?php echo htmlspecialchars($payment['txid']); ?>">
                                <button class="btn" style="padding:6px 10px;background:linear-gradient(135deg,#cf6679,#b00020);color:#fff;">Reject</button>
                            </form>
                        <?php else: ?>
                            <span style="color:#777;">Processed</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$depositPayments): ?><tr><td colspan="6" class="text-center">No DarkPay deposits yet.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once '../store/includes/footer.php'; ?>
