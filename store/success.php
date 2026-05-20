<?php
// success.php
require_once 'includes/db.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$game_name = "";
$card_value = "";
$quantity = 1;

if (isset($_GET['multiple']) && $_GET['multiple'] === 'true') {
    // Multi-item purchase logic
    $game_name = $_SESSION['success_game_name'] ?? '';
    $card_value = $_SESSION['success_card_value'] ?? '';
    $quantity = intval($_SESSION['success_quantity'] ?? 1);

    // Clear session details so they don't linger
    unset($_SESSION['success_game_name']);
    unset($_SESSION['success_card_value']);
    unset($_SESSION['success_quantity']);
    
    if (empty($game_name)) {
        header("Location: dashboard.php");
        exit();
    }
} else {
    // Single item fallback logic
    if (!isset($_GET['card_id'])) {
        header("Location: dashboard.php");
        exit();
    }
    $card_id = intval($_GET['card_id']);

    // Verify this user actually bought this card
    $stmt = $pdo->prepare("SELECT c.game_name, c.card_value 
                           FROM orders o 
                           JOIN cards c ON o.card_id = c.id 
                           WHERE o.card_id = ? AND o.user_id = ?");
    $stmt->execute([$card_id, $user_id]);
    $card = $stmt->fetch();

    if (!$card) {
        header("Location: dashboard.php");
        exit();
    }
    $game_name = $card['game_name'];
    $card_value = $card['card_value'];
    $quantity = 1;
}

$txid = $_SESSION['success_txid'] ?? '';
$is_verified = false;
$purchased_codes = [];

if (!empty($txid)) {
    $stmt_status = $pdo->prepare("SELECT status FROM orders WHERE txid = ? OR txid LIKE ? LIMIT 1");
    $stmt_status->execute([$txid, $txid . '-%']);
    $order_status = $stmt_status->fetchColumn();
    if ($order_status === 'verified') {
        $is_verified = true;
        
        $stmt_codes = $pdo->prepare("SELECT c.code 
                                     FROM orders o 
                                     JOIN cards c ON o.card_id = c.id 
                                     WHERE (o.txid = ? OR o.txid LIKE ?) AND o.user_id = ?");
        $stmt_codes->execute([$txid, $txid . '-%', $user_id]);
        $purchased_codes = $stmt_codes->fetchAll(PDO::FETCH_COLUMN);
    }
}
unset($_SESSION['success_txid']);

require_once 'includes/header.php';
?>

<div style="max-width: 600px; margin: 50px auto; text-align: center; background-color: #252525; padding: 40px; border-radius: 12px; border: 1px solid var(--secondary-color); box-shadow: 0 10px 30px rgba(3, 218, 198, 0.15);">
    
    <div style="font-size: 4.5rem; color: var(--secondary-color); margin-bottom: 20px; text-shadow: 0 0 20px rgba(3, 218, 198, 0.4);">
        ✓
    </div>
    
    <?php if ($is_verified): ?>
        <h1 style="color: var(--secondary-color); margin-bottom: 10px; font-weight: 800;">Payment Confirmed!</h1>
        <p style="font-size: 1.1rem; color: #ccc; margin-bottom: 30px;">Your purchase was instantly verified by **DarkPay**.</p>
    <?php else: ?>
        <h1 style="color: var(--secondary-color); margin-bottom: 10px; font-weight: 800;">Order Received!</h1>
        <p style="font-size: 1.1rem; color: #ccc; margin-bottom: 30px;">Thank you for your purchase. Your payment simulation is currently under review.</p>
    <?php endif; ?>
    
    <div style="background-color: #1a1a1a; padding: 25px; border-radius: 8px; border: 1px dashed rgba(3, 218, 198, 0.3); margin-bottom: 30px; text-align: left;">
        <h3 style="margin-bottom: 10px; font-size: 1.25rem; color: #fff; border-bottom: 1px solid #333; padding-bottom: 10px;">
            <?php echo htmlspecialchars($game_name); ?> - <?php echo htmlspecialchars($card_value); ?>
        </h3>
        <p style="color: #aaa; margin: 10px 0; font-size: 1.05rem;">
            Quantity Ordered: <strong style="color: var(--secondary-color);"><?php echo $quantity; ?></strong> unit<?php echo $quantity > 1 ? 's' : ''; ?>
        </p>
        
        <?php if ($is_verified): ?>
            <p style="color: var(--secondary-color); font-weight: bold; margin-bottom: 15px; display: inline-flex; align-items: center; gap: 5px;">
                <span style="display: inline-block; width: 8px; height: 8px; background: var(--secondary-color); border-radius: 50%; box-shadow: 0 0 10px var(--secondary-color);"></span>
                Status: Verified & Delivered
            </p>
            <div style="background: rgba(3, 218, 198, 0.05); border: 1px solid rgba(3, 218, 198, 0.2); border-radius: 6px; padding: 15px; margin-top: 15px;">
                <label style="color: var(--secondary-color); font-size: 0.85rem; text-transform: uppercase; font-weight: bold; display: block; margin-bottom: 10px; letter-spacing: 1px;">Prepaid Voucher Codes:</label>
                <?php foreach ($purchased_codes as $code): ?>
                    <div style="display: flex; gap: 10px; margin-bottom: 10px; align-items: center; background: #111; padding: 8px 12px; border-radius: 6px; border: 1px solid #222;">
                        <span style="font-family: monospace; font-size: 1.1rem; color: #fff; font-weight: bold; flex: 1; letter-spacing: 1px;"><?php echo htmlspecialchars($code); ?></span>
                        <button class="btn btn-secondary" onclick="navigator.clipboard.writeText('<?php echo htmlspecialchars(addslashes($code)); ?>'); alert('Code copied!');" style="padding: 4px 10px; font-size: 0.8rem; color: #000; border-radius: 4px; font-weight: bold; width: auto; min-width: auto; box-shadow: none;">Copy</button>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p style="color: orange; font-weight: bold; margin-bottom: 10px; display: inline-flex; align-items: center; gap: 5px;">
                <span style="display: inline-block; width: 8px; height: 8px; background: orange; border-radius: 50%; animate: pulse 1s infinite;"></span>
                Status: Pending Verification
            </p>
            <p style="font-size: 0.9rem; color: #888; line-height: 1.4;">
                Your secret voucher code(s) will be fully accessible in your personal dashboard once an administrator verifies the simulated checkout.
            </p>
        <?php endif; ?>
    </div>
    
    <a href="dashboard.php" class="btn btn-secondary" style="color: #000; border-radius: 6px; padding: 12px 35px; font-weight: bold; font-size: 1.05rem;">Go to Dashboard</a>
    
</div>

<?php require_once 'includes/footer.php'; ?>
