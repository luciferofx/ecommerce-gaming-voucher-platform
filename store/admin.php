<?php
// admin.php
require_once 'includes/db.php';
require_once 'includes/crypto_helper.php';
require_once 'includes/currency.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

require_once 'includes/header.php';

// Fetch all cards along with who purchased them (if sold)
$stmt = $pdo->query("SELECT c.*, u.username as purchaser 
                     FROM cards c 
                     LEFT JOIN orders o ON o.card_id = c.id 
                     LEFT JOIN users u ON o.user_id = u.id 
                     ORDER BY c.created_at DESC");
$cards = $stmt->fetchAll();

// Fetch all orders with card code and card_id
$stmt_orders = $pdo->query("SELECT o.id, u.username, c.id as card_id, c.code, c.game_name, c.card_value, c.price, o.purchase_date, o.status, o.payment_method, o.txid 
                            FROM orders o 
                            JOIN users u ON o.user_id = u.id 
                            JOIN cards c ON o.card_id = c.id 
                            ORDER BY o.purchase_date DESC");
$orders = $stmt_orders->fetchAll();

// Fetch all wallet deposits
$stmt_deposits = $pdo->query("SELECT d.id, u.username, d.amount, d.payment_method, d.txid, d.status, d.created_at 
                              FROM wallet_deposits d 
                              JOIN users u ON d.user_id = u.id 
                              ORDER BY d.created_at DESC");
$deposits = $stmt_deposits->fetchAll();

// Fetch all registered users
$stmt_users = $pdo->query("SELECT id, username, role, wallet_balance, login_token FROM users ORDER BY id ASC");
$users = $stmt_users->fetchAll();
$rates = currencyRates($pdo);
$rates_updated_at = currencyLastUpdated($pdo);
?>

<h1 style="margin-bottom: 25px;">Admin Dashboard Cockpit</h1>

<!-- TOP GLOBAL ALERTS -->
<?php
if (isset($_SESSION['add_success'])) {
    echo "<div class='alert alert-success' style='margin-bottom: 25px;'>" . $_SESSION['add_success'] . "</div>";
    unset($_SESSION['add_success']);
}
if (isset($_SESSION['add_error'])) {
    echo "<div class='alert alert-error' style='margin-bottom: 25px;'>" . $_SESSION['add_error'] . "</div>";
    unset($_SESSION['add_error']);
}
?>

<h2 style="color: var(--secondary-color); margin-bottom: 20px; font-weight: 700; text-shadow: 0 0 10px rgba(3,218,198,0.15);">Live Currency Rates</h2>
<div class="form-container glass-container" style="margin: 0 0 35px 0; max-width: 100%; border: 1px solid rgba(3,218,198,0.15);">
    <form action="process_action.php?action=refresh_currency_rates" method="POST" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; align-items: end;">
        <div>
            <div style="color: #888; font-size: 0.85rem; margin-bottom: 5px;">1 USD</div>
            <div style="font-size: 1.3rem; font-weight: 800; color: #fff;">$1.00 USD</div>
        </div>
        <div>
            <div style="color: #888; font-size: 0.85rem; margin-bottom: 5px;">EUR Rate</div>
            <div style="font-size: 1.3rem; font-weight: 800; color: #fff;">€<?php echo number_format($rates['EUR'], 4); ?> EUR</div>
        </div>
        <div>
            <div style="color: #888; font-size: 0.85rem; margin-bottom: 5px;">INR Rate</div>
            <div style="font-size: 1.3rem; font-weight: 800; color: #fff;">₹<?php echo number_format($rates['INR'], 4); ?> INR</div>
        </div>
        <button type="submit" class="btn btn-secondary" style="color: #000; border-radius: 6px; padding: 12px; font-weight: bold;">Refresh Rates</button>
    </form>
    <p style="color: #888; margin-top: 12px; font-size: 0.9rem;">Users choose USD, EUR, or INR from the navbar. Rates auto-refresh hourly from live exchange APIs and fall back to cached values. Last update: <?php echo htmlspecialchars($rates_updated_at); ?>.</p>
</div>

<!-- ROW 1: USER ACCOUNTS VAULT & USER CREATION -->
<h2 style="color: var(--secondary-color); margin-bottom: 20px; font-weight: 700; text-shadow: 0 0 10px rgba(3,218,198,0.15);">👤 User Accounts Vault</h2>
<div class="admin-grid-layout">
    <!-- Create New User Form -->
    <div class="form-container glass-container" style="margin: 0; max-width: 100%; border: 1px solid rgba(187,134,252,0.15);">
        <h3 style="margin-bottom: 20px; color: #fff;">Create User Account</h3>
        <form action="process_action.php?action=create_user" method="POST">
            <div class="form-group" style="margin-bottom: 15px;">
                <label>Username</label>
                <input type="text" name="username" placeholder="Enter secure handle..." required style="background-color: #1a1a1a; border: 1px solid #444; border-radius: 6px; padding: 12px; width: 100%; color: #fff;">
            </div>
            <div class="form-group" style="margin-bottom: 15px;">
                <label>Password</label>
                <input type="password" name="password" placeholder="Enter secure password..." required style="background-color: #1a1a1a; border: 1px solid #444; border-radius: 6px; padding: 12px; width: 100%; color: #fff;">
            </div>
            <div class="form-group" style="margin-bottom: 15px;">
                <label>System Role</label>
                <select name="role" style="background-color: #1a1a1a; border: 1px solid #444; border-radius: 6px; padding: 12px; width: 100%; color: #fff;">
                    <option value="user">Standard User</option>
                    <option value="admin">Admin Operator</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom: 20px;">
                <label>Starting Wallet Balance (USD base)</label>
                <input type="number" step="0.01" name="wallet_balance" value="0.00" style="background-color: #1a1a1a; border: 1px solid #444; border-radius: 6px; padding: 12px; width: 100%; color: #fff;">
            </div>
            <button type="submit" class="btn" style="width: 100%; border-radius: 6px; padding: 12px; font-weight: bold; box-shadow: 0 4px 15px rgba(187, 134, 252, 0.2);">Create Account</button>
        </form>
    </div>

    <!-- Users Ledger Directory Table -->
    <div>
        <div style="overflow-x: auto; max-height: 400px; border-radius: 8px; border: 1px solid #333;">
            <table style="margin: 0;">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Wallet Balance</th>
                        <th>Access Token Prefix</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td>#<?php echo $u['id']; ?></td>
                            <td style="font-weight: 600; color: #fff;"><?php echo htmlspecialchars($u['username']); ?></td>
                            <td>
                                <?php if ($u['role'] === 'admin'): ?>
                                    <span style="color: var(--primary-color); font-weight: bold; background: rgba(187,134,252,0.1); padding: 2px 6px; border-radius: 4px;">Admin</span>
                                <?php else: ?>
                                    <span style="color: #aaa;">User</span>
                                <?php endif; ?>
                            </td>
                            <td style="color: var(--secondary-color); font-weight: bold;"><?php echo htmlspecialchars(currencyFormat($pdo, $u['wallet_balance'])); ?></td>
                            <td style="font-family: monospace; font-size: 0.8rem; color: #888;">
                                <?php echo htmlspecialchars(substr($u['login_token'], 0, 15)); ?>...
                            </td>
                            <td style="text-align: right;">
                                <button type="button" class="btn btn-secondary" 
                                        onclick="openUserModal(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['username'], ENT_QUOTES); ?>', <?php echo $u['wallet_balance']; ?>, '<?php echo $u['role']; ?>')" 
                                        style="padding: 5px 12px; font-size: 0.8rem; color: #000; font-weight: bold; border-radius: 4px;">
                                    Manage
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ROW 2: PRODUCTS STOCK & ADD NEW CARD -->
<h2 style="color: var(--primary-color); margin-bottom: 20px; font-weight: 700; text-shadow: 0 0 10px rgba(187,134,252,0.15);">💳 Card Products Stock Inventory</h2>
<div class="admin-grid-layout">
    <!-- Add New Card Form -->
    <div class="form-container glass-container" style="margin: 0; max-width: 100%; border: 1px solid rgba(187,134,252,0.15);">
        <h3>Add New Game Card</h3>
        <form action="process_action.php?action=add_card" method="POST">
            <div class="form-group" style="margin-bottom: 12px;">
                <label>Card Type - VISA , Master Card , Rupay Card , JCB , Amex </label>
                <input type="text" name="game_name" required style="background-color: #1a1a1a; border: 1px solid #444; border-radius: 6px; padding: 12px; width: 100%; color: #fff;">
            </div>
            <div class="form-group" style="margin-bottom: 12px;">
                <label>Card Value (e.g., $100, $1000)</label>
                <input type="text" name="card_value" required style="background-color: #1a1a1a; border: 1px solid #444; border-radius: 6px; padding: 12px; width: 100%; color: #fff;">
            </div>
            <div class="form-group" style="margin-bottom: 12px;">
                <label>Price (USD base)</label>
                <input type="number" step="0.01" name="price" required style="background-color: #1a1a1a; border: 1px solid #444; border-radius: 6px; padding: 12px; width: 100%; color: #fff;">
            </div>
            <div class="form-group" style="margin-bottom: 12px;">
                <label>Quantity to Stock (Add Multiple)</label>
                <input type="number" name="quantity" value="1" min="1" max="100" required style="background-color: #1a1a1a; border: 1px solid #444; border-radius: 6px; padding: 12px; width: 100%; color: #fff;">
            </div>
            <div class="form-group" style="margin-bottom: 20px;">
                <label>Card Details / Prepaid Secret Code</label>
                <input type="text" name="code" required style="background-color: #1a1a1a; border: 1px solid #444; border-radius: 6px; padding: 12px; width: 100%; color: #fff;">
            </div>
            <button type="submit" class="btn" style="width: 100%; border-radius: 6px; padding: 12px; font-weight: bold; box-shadow: 0 4px 15px rgba(187, 134, 252, 0.2);">Add Card Vouchers</button>
        </form>
    </div>

    <!-- Inventory overview -->
    <div>
        <div style="overflow-x: auto; max-height: 460px; border-radius: 8px; border: 1px solid #333;">
            <table style="margin: 0;">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Game/Type</th>
                        <th>Value</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Purchased By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cards as $card): ?>
                        <tr>
                            <td>#<?php echo $card['id']; ?></td>
                            <td style="font-weight: 600; color: #fff;"><?php echo htmlspecialchars($card['game_name']); ?></td>
                            <td><?php echo htmlspecialchars($card['card_value']); ?></td>
                            <td><?php echo htmlspecialchars(currencyFormat($pdo, $card['price'])); ?></td>
                            <td>
                                <?php if ($card['status'] === 'available'): ?>
                                    <span style="color: var(--secondary-color); font-weight: bold;">Available</span>
                                <?php else: ?>
                                    <span style="color: var(--danger); font-weight: bold;">Sold</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($card['purchaser'])): ?>
                                    <span style="color: #fff; font-weight: bold;">👤 <?php echo htmlspecialchars($card['purchaser']); ?></span>
                                <?php else: ?>
                                    <span style="color: #666; font-style: italic;">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ROW 3: RECENT ORDERS AUDITING & VOUCHER EDITS -->
<h3 style="margin-top: 40px; margin-bottom: 20px; font-weight: bold; font-size: 1.3rem; color: #fff;">🛍️ Recent Card Orders & Voucher Codes</h3>
<div style="overflow-x: auto; margin-bottom: 50px; border-radius: 8px; border: 1px solid #333;">
    <table style="margin: 0;">
        <thead>
            <tr>
                <th>Order ID</th>
                <th>User</th>
                <th>Game Card</th>
                <th>Secret Prepaid Voucher Code</th>
                <th>Price Paid</th>
                <th>Payment / TXID</th>
                <th>Status</th>
                <th style="text-align: right;">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($orders as $order): ?>
                <tr>
                    <td>#<?php echo $order['id']; ?></td>
                    <td style="font-weight: 600; color: #fff;"><?php echo htmlspecialchars($order['username']); ?></td>
                    <td><?php echo htmlspecialchars($order['game_name'] . ' - ' . $order['card_value']); ?></td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span style="font-family: monospace; font-size: 0.9rem; color: orange; font-weight: bold; background: rgba(255,165,0,0.05); padding: 4px 8px; border-radius: 4px; border: 1px dashed rgba(255,165,0,0.15);">
                                <?php echo htmlspecialchars(substr($order['code'], 0, 18)); ?><?php if (strlen($order['code']) > 18) echo "..."; ?>
                            </span>
                            <button type="button" class="btn" 
                                    onclick="openCardCodeModal(<?php echo $order['card_id']; ?>, '<?php echo htmlspecialchars($order['game_name'] . ' (' . $order['card_value'] . ')', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($order['code'], ENT_QUOTES); ?>')" 
                                    style="padding: 2px 8px; font-size: 0.75rem; background: #333; border: 1px solid #555; color: #ccc; border-radius: 4px;">
                                ✏️ Edit
                            </button>
                        </div>
                    </td>
                    <td style="font-weight: bold; color: #fff;"><?php echo htmlspecialchars(currencyFormat($pdo, $order['price'])); ?></td>
                    <td>
                        <?php 
                        if ($order['payment_method'] === 'credit_card' || empty($order['payment_method'])) {
                            echo "💳 Card";
                        } elseif ($order['payment_method'] === 'wallet') {
                            echo "👛 Wallet Balance";
                        } else {
                            $clean_method = CRYPTO_NAMES[$order['payment_method']] ?? $order['payment_method'];
                            echo "🪙 " . htmlspecialchars($clean_method);
                            if (!empty($order['txid'])) {
                                $clean_tx = explode('-', $order['txid'])[0];
                                $exp_url = getExplorerUrl($clean_tx, $order['payment_method']);
                                if (!empty($exp_url)) {
                                    echo "<a href='$exp_url' target='_blank' style='color: var(--secondary-color); font-size: 0.8rem; text-decoration: underline; display: block; margin-top: 4px;'>Tx: " . htmlspecialchars(substr($clean_tx, 0, 10)) . "...</a>";
                                } else {
                                    echo "<span style='color: #888; font-size: 0.8rem; display: block; margin-top: 4px;'>Tx: " . htmlspecialchars(substr($clean_tx, 0, 10)) . "...</span>";
                                }
                            }
                        }
                        ?>
                    </td>
                    <td>
                        <?php if ($order['status'] === 'verified'): ?>
                            <span style="color: var(--secondary-color); font-weight: bold; background-color: rgba(3, 218, 198, 0.1); padding: 3px 8px; border-radius: 4px; border: 1px solid rgba(3, 218, 198, 0.2);">Verified</span>
                        <?php elseif ($order['status'] === 'rejected'): ?>
                            <span style="color: var(--danger); font-weight: bold; background-color: rgba(207, 102, 121, 0.1); padding: 3px 8px; border-radius: 4px; border: 1px solid rgba(207, 102, 121, 0.2);">Rejected</span>
                        <?php else: ?>
                            <span style="color: orange; font-weight: bold; background-color: rgba(255, 165, 0, 0.1); padding: 3px 8px; border-radius: 4px; border: 1px solid rgba(255, 165, 0, 0.2);">Pending</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align: right;">
                        <?php if ($order['status'] === 'pending'): ?>
                            <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                <form action="process_action.php?action=verify_order" method="POST" style="margin: 0;">
                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                    <button type="submit" class="btn btn-secondary"
                                        style="padding: 5px 12px; font-size: 0.85rem; color: #000; border-radius: 4px; font-weight: bold;">Verify</button>
                                </form>
                                <form action="process_action.php?action=reject_order" method="POST" style="margin: 0;">
                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                    <button type="submit" class="btn"
                                        style="padding: 5px 12px; font-size: 0.85rem; background: linear-gradient(135deg, #cf6679, #b00020); color: #fff; border: none; border-radius: 4px; font-weight: bold;">Reject</button>
                                </form>
                            </div>
                        <?php else: ?>
                            <span style="color: #666; font-size: 0.9rem; font-style: italic;">Processed</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($orders)): ?>
                <tr>
                    <td colspan="8" class="text-center">No orders yet.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- ROW 4: WALLET DEPOSITS LEDGER -->
<h3 style="margin-top: 50px; margin-bottom: 20px; font-weight: bold; font-size: 1.3rem; color: #fff;">💸 Wallet Deposits Audit Queue</h3>
<div style="overflow-x: auto; margin-bottom: 40px; border-radius: 8px; border: 1px solid #333;">
    <table style="margin: 0;">
        <thead>
            <tr>
                <th>Deposit ID</th>
                <th>User</th>
                <th>Amount (USD)</th>
                <th>Payment Method / TXID</th>
                <th>Date</th>
                <th>Status</th>
                <th style="text-align: right;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($deposits as $dep): ?>
                <tr>
                    <td>#<?php echo $dep['id']; ?></td>
                    <td style="font-weight: 600; color: #fff;"><?php echo htmlspecialchars($dep['username']); ?></td>
                    <td style="font-weight: bold; color: var(--secondary-color); font-size: 1.05rem;"><?php echo htmlspecialchars(currencyFormat($pdo, $dep['amount'])); ?></td>
                    <td>
                        <?php 
                        if ($dep['payment_method'] === 'credit_card') {
                            echo "💳 Card Simulation";
                        } else {
                            $clean_lbl = CRYPTO_NAMES[$dep['payment_method']] ?? $dep['payment_method'];
                            echo "🪙 " . htmlspecialchars($clean_lbl);
                            if (!empty($dep['txid'])) {
                                $exp_url = getExplorerUrl($dep['txid'], $dep['payment_method']);
                                if (!empty($exp_url)) {
                                    echo "<a href='$exp_url' target='_blank' style='color: var(--secondary-color); font-size: 0.8rem; text-decoration: underline; display: block; margin-top: 4px;'>Tx: " . htmlspecialchars(substr($dep['txid'], 0, 10)) . "...</a>";
                                } else {
                                    echo "<span style='color: #888; font-size: 0.8rem; display: block; margin-top: 4px;'>Tx: " . htmlspecialchars(substr($dep['txid'], 0, 10)) . "...</span>";
                                }
                            }
                        }
                        ?>
                    </td>
                    <td style="color: #aaa;"><?php echo date('M d, Y H:i', strtotime($dep['created_at'])); ?></td>
                    <td>
                        <?php if ($dep['status'] === 'verified'): ?>
                            <span style="color: var(--secondary-color); font-weight: bold; background-color: rgba(3, 218, 198, 0.1); padding: 3px 8px; border-radius: 4px; border: 1px solid rgba(3, 218, 198, 0.2);">Verified</span>
                        <?php elseif ($dep['status'] === 'rejected'): ?>
                            <span style="color: var(--danger); font-weight: bold; background-color: rgba(207, 102, 121, 0.1); padding: 3px 8px; border-radius: 4px; border: 1px solid rgba(207, 102, 121, 0.2);">Rejected</span>
                        <?php else: ?>
                            <span style="color: orange; font-weight: bold; background-color: rgba(255, 165, 0, 0.1); padding: 3px 8px; border-radius: 4px; border: 1px solid rgba(255, 165, 0, 0.2);">Pending</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align: right;">
                        <?php if ($dep['status'] === 'pending'): ?>
                            <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                <form action="process_action.php?action=approve_deposit" method="POST" style="margin: 0;">
                                    <input type="hidden" name="deposit_id" value="<?php echo $dep['id']; ?>">
                                    <button type="submit" class="btn btn-secondary" style="padding: 5px 12px; font-size: 0.85rem; color: #000; border-radius: 4px; font-weight: bold;">Approve</button>
                                </form>
                                <form action="process_action.php?action=reject_deposit" method="POST" style="margin: 0;">
                                    <input type="hidden" name="deposit_id" value="<?php echo $dep['id']; ?>">
                                    <button type="submit" class="btn" style="padding: 5px 12px; font-size: 0.85rem; background: linear-gradient(135deg, #cf6679, #b00020); color: #fff; border: none; border-radius: 4px; font-weight: bold;">Reject</button>
                                </form>
                            </div>
                        <?php else: ?>
                            <span style="color: #666; font-size: 0.9rem; font-style: italic;">Processed</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($deposits)): ?>
                <tr>
                    <td colspan="7" class="text-center">No deposit transactions logged yet.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- ========================================== -->
<!--       PREMIUM ACTION OVERLAYS MODALS       -->
<!-- ========================================== -->

<!-- Modal 1: Manage User Details (Contains edits & delete account option) -->
<div id="userModal" class="modal" style="display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.85); align-items: center; justify-content: center;">
    <div class="modal-content glass-container" style="border: 1px solid var(--secondary-color); max-width: 450px; width: 90%; padding: 30px; border-radius: 12px; position: relative;">
        <span class="close-modal" onclick="closeUserModal()" style="position: absolute; right: 20px; top: 15px; font-size: 1.8rem; color: #888; cursor: pointer;">&times;</span>
        <h2 style="margin-bottom: 10px; color: var(--secondary-color); font-weight: 700; font-size: 1.5rem;">Manage User Account</h2>
        <p style="color: #aaa; font-size: 0.9rem; margin-bottom: 25px; line-height: 1.4;">Update active wallet balance, system role permissions, reset the password, or permanently delete the account for <strong id="user_modal_title" style="color: #fff;">Username</strong>.</p>
        
        <!-- Action 1: Save User changes -->
        <form action="process_action.php?action=edit_user" method="POST" style="margin-bottom: 15px;">
            <input type="hidden" name="target_user_id" id="user_modal_id">
            
            <div class="form-group" style="margin-bottom: 18px;">
                <label style="color: #ccc; font-weight: 500; display: block; margin-bottom: 6px; font-size: 0.9rem;">Wallet Balance (USD)</label>
                <input type="number" step="0.01" name="wallet_balance" id="user_modal_balance" style="width: 100%; padding: 12px; background-color: #1a1a1a; border: 1px solid #444; color: var(--secondary-color); font-weight: bold; border-radius: 6px; font-size: 1.1rem;" required>
            </div>
            
            <div class="form-group" style="margin-bottom: 18px;">
                <label style="color: #ccc; font-weight: 500; display: block; margin-bottom: 6px; font-size: 0.9rem;">System Role</label>
                <select name="role" id="user_modal_role" style="width: 100%; padding: 12px; background-color: #1a1a1a; border: 1px solid #444; color: white; border-radius: 6px;" required>
                    <option value="user">Standard User</option>
                    <option value="admin">Admin Operator</option>
                </select>
            </div>
            
            <div class="form-group" style="margin-bottom: 25px;">
                <label style="color: #ccc; font-weight: 500; display: block; margin-bottom: 6px; font-size: 0.9rem;">New Password (Optional)</label>
                <input type="password" name="password" placeholder="Leave blank to keep existing password..." style="width: 100%; padding: 12px; background-color: #1a1a1a; border: 1px solid #444; color: white; border-radius: 6px;">
            </div>

            <button type="submit" class="btn btn-secondary" style="width: 100%; border-radius: 6px; font-size: 1.05rem; padding: 12px; color: #000; font-weight: bold; box-shadow: 0 4px 15px rgba(3, 218, 198, 0.2);">Save Vault Modifications</button>
        </form>

        <!-- Action 2: Permanent Account Deletion -->
        <form action="process_action.php?action=delete_user" method="POST" onsubmit="return confirm('WARNING: Are you absolutely sure you want to permanently delete this user account? All order logs, wallet deposits, and active history will be completely erased. This is irreversible!')">
            <input type="hidden" name="target_user_id" id="delete_modal_id">
            <button type="submit" class="btn" style="width: 100%; border-radius: 6px; font-size: 1.05rem; padding: 12px; background: linear-gradient(135deg, #cf6679, #b00020); color: #fff; font-weight: bold; border: none; box-shadow: 0 4px 15px rgba(207, 102, 121, 0.2);">🔴 Delete Account Permanently</button>
        </form>
    </div>
</div>

<!-- Modal 2: Edit Card Prepaid voucher Code -->
<div id="cardCodeModal" class="modal" style="display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.85); align-items: center; justify-content: center;">
    <div class="modal-content glass-container" style="border: 1px solid var(--primary-color); max-width: 450px; width: 90%; padding: 30px; border-radius: 12px; position: relative;">
        <span class="close-modal" onclick="closeCardCodeModal()" style="position: absolute; right: 20px; top: 15px; font-size: 1.8rem; color: #888; cursor: pointer;">&times;</span>
        <h2 style="margin-bottom: 10px; color: var(--primary-color); font-weight: 700; font-size: 1.5rem;">Edit Card Code</h2>
        <p style="color: #aaa; font-size: 0.9rem; margin-bottom: 25px; line-height: 1.4;">Modify the pre-paid secret card voucher details for: <strong id="code_modal_product" style="color: #fff;">Product Title</strong>.</p>
        
        <form action="process_action.php?action=edit_card_code" method="POST">
            <input type="hidden" name="card_id" id="code_modal_card_id">
            
            <div class="form-group" style="margin-bottom: 25px;">
                <label style="color: #ccc; font-weight: 500; display: block; margin-bottom: 8px;">Prepaid Secret Code</label>
                <textarea name="code" id="code_modal_text" rows="3" style="width: 100%; padding: 12px; background-color: #1a1a1a; border: 1px solid #444; color: white; border-radius: 6px; resize: none; font-family: monospace; font-size: 1.1rem; letter-spacing: 1px;" required></textarea>
            </div>

            <button type="submit" class="btn" style="width: 100%; border-radius: 6px; font-size: 1.05rem; padding: 12px; box-shadow: 0 4px 15px rgba(187, 134, 252, 0.2);">Update Voucher Code</button>
        </form>
    </div>
</div>

<script>
    // Manage User modal routines
    function openUserModal(id, username, balance, role) {
        document.getElementById('user_modal_id').value = id;
        document.getElementById('delete_modal_id').value = id; // Set delete ID
        document.getElementById('user_modal_title').innerText = username;
        document.getElementById('user_modal_balance').value = balance;
        document.getElementById('user_modal_role').value = role;
        document.getElementById('userModal').style.display = "flex";
    }
    
    function closeUserModal() {
        document.getElementById('userModal').style.display = "none";
    }

    // Edit Voucher code modal routines
    function openCardCodeModal(cardId, productName, currentCode) {
        document.getElementById('code_modal_card_id').value = cardId;
        document.getElementById('code_modal_product').innerText = productName;
        document.getElementById('code_modal_text').value = currentCode;
        document.getElementById('cardCodeModal').style.display = "flex";
    }

    function closeCardCodeModal() {
        document.getElementById('cardCodeModal').style.display = "none";
    }

    // Close modals on screen click outside
    window.onclick = function (event) {
        const userM = document.getElementById('userModal');
        const codeM = document.getElementById('cardCodeModal');
        if (event.target == userM) {
            closeUserModal();
        }
        if (event.target == codeM) {
            closeCardCodeModal();
        }
    }
</script>

<?php require_once 'includes/footer.php'; ?>
