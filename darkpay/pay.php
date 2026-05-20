<?php
// pay.php - DarkPay Gateway Interface
require_once '../store/includes/db.php';
require_once 'includes/darkpay_gateway.php';

// Retrieve transaction details
$txid = $_GET['txid'] ?? '';
$amount = floatval($_GET['amount'] ?? 0.00);
$type = $_GET['type'] ?? 'checkout'; // 'checkout' or 'deposit'
$currency = strtoupper($_GET['currency'] ?? 'INR');
if ($currency !== 'INR') {
    $currency = 'INR';
}

if (empty($txid) || $amount <= 0.00) {
    die("<div style='background-color:#1e1e24;color:#ff6b6b;font-family:sans-serif;padding:30px;text-align:center;border-radius:8px;border:1px solid #ff6b6b;margin:50px auto;max-width:500px;'><h3>Invalid Transaction Request</h3><p>Transaction ID and valid amount parameters are required to utilize the DarkPay Gateway.</p><a href='../store/dashboard.php' style='color:#fff;text-decoration:underline;'>Return to Store Dashboard</a></div>");
}

darkpayEnsureSchema($pdo);

// Check database to verify transaction exists
if ($type === 'deposit') {
    $stmt = $pdo->prepare("SELECT status FROM wallet_deposits WHERE txid = ?");
    $stmt->execute([$txid]);
    $txn_status = $stmt->fetchColumn();
} else {
    $stmt = $pdo->prepare("SELECT status FROM orders WHERE txid = ? OR txid LIKE ? LIMIT 1");
    $stmt->execute([$txid, $txid . '-%']);
    $txn_status = $stmt->fetchColumn();
}

if (!$txn_status) {
    die("<div style='background-color:#1e1e24;color:#ff3366;font-family:sans-serif;padding:30px;text-align:center;border-radius:8px;border:1px solid #ff3366;margin:50px auto;max-width:500px;'><h3>Transaction Reference Not Found</h3><p>The requested transaction ID (<strong>" . htmlspecialchars($txid) . "</strong>) does not match any pending records in the database.</p><a href='../store/dashboard.php' style='color:#fff;text-decoration:underline;'>Return to Store Dashboard</a></div>");
}

if ($txn_status === 'verified') {
    die("<div style='background-color:#1e1e24;color:#33cc66;font-family:sans-serif;padding:30px;text-align:center;border-radius:8px;border:1px solid #33cc66;margin:50px auto;max-width:500px;'><h3>Transaction Already Completed</h3><p>This transaction has already been verified and processed.</p><a href='../store/dashboard.php' style='color:#fff;text-decoration:underline;'>Go to Dashboard</a></div>");
}

$merchantVpa = darkpayConfig($pdo, 'upi_vpa', 'DARKPAY_UPI_VPA', 'raja9311@ptaxis');
$merchantName = darkpayConfig($pdo, 'merchant_name', 'DARKPAY_MERCHANT_NAME', 'Dark Gaming Store');
$upiUrl = 'upi://pay?pa=' . rawurlencode($merchantVpa)
    . '&pn=' . rawurlencode($merchantName)
    . '&tr=' . rawurlencode($txid)
    . '&tn=' . rawurlencode('DarkPay ' . $txid)
    . '&am=' . rawurlencode(number_format($amount, 2, '.', ''))
    . '&cu=INR';
$qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' . rawurlencode($upiUrl);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DarkPay Secure Gateway</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/pay_style.css?v=<?php echo time(); ?>">
</head>
<body>
    <!-- Background slow pulsing circles -->
    <div class="ambient-glow orb-purple"></div>
    <div class="ambient-glow orb-cyan"></div>

    <div class="gateway-container">
        <!-- Secure Header Badge -->
        <div class="secure-badge">
            <span class="lock-icon">🔒</span>
            <span>SECURE 256-BIT SSL ENCRYPTED GATEWAY</span>
        </div>

        <div class="gateway-card glass-container">
            <!-- Merchant Details -->
            <div class="merchant-header">
                <div>
                    <h2 class="merchant-name"><?php echo htmlspecialchars($merchantName); ?></h2>
                    <p class="txn-ref">REF: <?php echo htmlspecialchars($txid); ?></p>
                </div>
                <div class="amount-badge">
                    <span class="currency">₹</span>
                    <span class="value"><?php echo number_format($amount, 2); ?></span>
                </div>
            </div>

            <!-- Payment Tabs -->
            <div class="pay-tabs">
                <button type="button" class="tab-btn active" onclick="switchTab('upi')">UPI</button>
            </div>

            <!-- Forms -->
            <form action="process_payment.php" method="POST" id="payment_form" onsubmit="handlePaymentSubmit(event)" data-status-url="status.php?type=<?php echo urlencode($type); ?>&txid=<?php echo urlencode($txid); ?>" data-success-url="<?php echo $type === 'deposit' ? '../store/dashboard.php' : '../store/success.php?multiple=true'; ?>" data-dashboard-url="../store/dashboard.php">
                <input type="hidden" name="txid" value="<?php echo htmlspecialchars($txid); ?>">
                <input type="hidden" name="amount" value="<?php echo $amount; ?>">
                <input type="hidden" name="type" value="<?php echo htmlspecialchars($type); ?>">
                <input type="hidden" name="payment_method" id="selected_method" value="upi">

                <!-- CARD PANEL -->
                <div id="panel_card" class="payment-panel">
                    <!-- Virtual Card Graphic Wrapper -->
                    <div class="virtual-card-wrapper">
                        <div class="virtual-card">
                            <div class="card-front">
                                <div class="card-chip"></div>
                                <div class="card-brand" id="card_logo">DarkPay</div>
                                <div class="card-number-display" id="card_num_display">•••• •••• •••• ••••</div>
                                <div class="card-bottom">
                                    <div class="card-holder-display">
                                        <div class="label">CARD HOLDER</div>
                                        <div class="value" id="card_name_display">YOUR NAME</div>
                                    </div>
                                    <div class="card-expiry-display">
                                        <div class="label">EXPIRES</div>
                                        <div class="value" id="card_expiry_display">MM/YY</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Input Fields -->
                    <div class="form-group">
                        <label>Cardholder Name</label>
                        <input type="text" name="card_name" id="card_name_input" placeholder="e.g. John Doe" oninput="updateCardGraphic()">
                    </div>
                    <div class="form-group">
                        <label>Card Number</label>
                        <div class="input-with-logo">
                            <input type="text" name="card_number" id="card_num_input" placeholder="4111 2222 3333 4444" maxlength="19" oninput="formatCardNumber(this); updateCardGraphic();">
                            <span class="inline-brand-icon" id="inline_brand_icon">💳</span>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Expiry Date</label>
                            <input type="text" name="card_expiry" id="card_expiry_input" placeholder="MM/YY" maxlength="5" oninput="formatExpiry(this); updateCardGraphic();">
                        </div>
                        <div class="form-group">
                            <label>CVV / CVN</label>
                            <input type="password" name="card_cvv" id="card_cvv_input" placeholder="•••" maxlength="3">
                        </div>
                    </div>
                </div>

                <!-- UPI PANEL -->
                <div id="panel_upi" class="payment-panel active">
                    <div class="upi-logo-container">
                        <div class="upi-glow-logo">UPI</div>
                        <p class="upi-tagline">Pay to <?php echo htmlspecialchars($merchantVpa); ?></p>
                    </div>
                    <div class="upi-qr-box">
                        <img src="<?php echo htmlspecialchars($qrUrl); ?>" alt="UPI payment QR">
                        <a class="upi-pay-link" href="<?php echo htmlspecialchars($upiUrl); ?>">Open UPI App</a>
                    </div>
                    <div class="form-group">
                        <label>Enter UPI ID (VPA)</label>
                        <input type="text" name="upi_id" id="upi_id_input" placeholder="username@upi" pattern="[a-zA-Z0-9.\-_]{2,256}@[a-zA-Z]{2,64}">
                        <span class="input-helper">Example: john@ybl, 9876543210@paytm</span>
                    </div>
                    <div class="form-group">
                        <label>UPI Transaction / UTR Number (Optional)</label>
                        <input type="text" name="customer_upi_ref" id="customer_upi_ref" placeholder="12 digit UTR or bank reference" minlength="8">
                        <span class="input-helper">Optional support reference. Auto approval happens from provider webhook verification.</span>
                    </div>

                    <div class="realtime-status" id="realtime_status">
                        Scan the QR and complete payment. This page will auto-verify when the provider confirms success.
                    </div>
                </div>

                <!-- NET BANKING PANEL -->
                <div id="panel_netbank" class="payment-panel">
                    <label style="margin-bottom: 12px; display: block; font-weight: 500; color: #ccc;">Select Bank</label>
                    <div class="bank-grid">
                        <div class="bank-card" onclick="selectBank('db', 'DarkBank Central')">
                            <div class="bank-icon font-glow">DB</div>
                            <div class="bank-name">DarkBank Central</div>
                        </div>
                        <div class="bank-card" onclick="selectBank('sbi', 'State Bank of India')">
                            <div class="bank-icon text-cyan">SBI</div>
                            <div class="bank-name">State Bank of India</div>
                        </div>
                        <div class="bank-card" onclick="selectBank('hdfc', 'HDFC Bank')">
                            <div class="bank-icon text-blue">HDFC</div>
                            <div class="bank-name">HDFC Bank Ltd</div>
                        </div>
                        <div class="bank-card" onclick="selectBank('icici', 'ICICI Bank')">
                            <div class="bank-icon text-orange">ICICI</div>
                            <div class="bank-name">ICICI Union Bank</div>
                        </div>
                    </div>
                    <input type="hidden" name="bank_code" id="selected_bank_code" value="">
                    <p id="bank_selected_text" class="bank-selection-status">No bank selected</p>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="submit-btn" id="pay_btn">
                    I Have Paid / Check Status
                </button>
            </form>

            <!-- Back navigation link -->
            <a href="../store/checkout.php?card_id=<?php
                // Get the card ID for redirection fallback
                if ($type === 'deposit') {
                    echo 'dashboard';
                } else {
                    // Try to fetch card_id from DB
                    $stmt_cid = $pdo->prepare("SELECT card_id FROM orders WHERE txid = ? OR txid LIKE ? LIMIT 1");
                    $stmt_cid->execute([$txid, $txid . '-%']);
                    $cid = $stmt_cid->fetchColumn();
                    echo $cid ? $cid : 'dashboard';
                }
            ?>" class="back-link">Cancel & Return to Store</a>
        </div>
    </div>

    <!-- Processing Overlay Modal -->
    <div id="processing_modal" class="modal-overlay">
        <div class="modal-content glass-container">
            <div class="spinner-container">
                <div class="glow-spinner"></div>
                <div class="secure-check-mark" id="modal_checkmark">✓</div>
            </div>
            <h3 id="modal_title" class="processing-title">Securing Connection...</h3>
            <p id="modal_subtitle" class="processing-subtitle">Establishing end-to-end security handshake with bank vault.</p>
            
            <div class="stepper">
                <div class="step active" id="step_1">Connection</div>
                <div class="step" id="step_2">Validation</div>
                <div class="step" id="step_3">Authorization</div>
            </div>
        </div>
    </div>

    <script src="assets/js/pay.js?v=<?php echo time(); ?>"></script>
</body>
</html>
