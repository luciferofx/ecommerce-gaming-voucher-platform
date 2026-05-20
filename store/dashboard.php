<?php
// dashboard.php
require_once 'includes/db.php';
require_once 'includes/currency.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'includes/header.php';

$user_id = $_SESSION['user_id'];

// Fetch user metadata (wallet balance, access token)
$stmt_user = $pdo->prepare("SELECT wallet_balance, login_token FROM users WHERE id = ?");
$stmt_user->execute([$user_id]);
$user_data = $stmt_user->fetch();
$wallet_balance = floatval($user_data['wallet_balance'] ?? 0.00);
$login_token = $user_data['login_token'] ?? '';
$display_currency = currencyDisplayCode($pdo);
$display_symbols = ['USD' => '$', 'EUR' => '€', 'INR' => '₹'];
$display_symbol = $display_symbols[$display_currency] ?? '$';
$deposit_min_display = currencyConvertFromUsd($pdo, 5, $display_currency);
$deposit_max_display = currencyConvertFromUsd($pdo, 5000, $display_currency);
$deposit_default_display = currencyConvertFromUsd($pdo, 50, $display_currency);

// Fetch user's purchased cards and any existing review details
$stmt_purchased = $pdo->prepare("SELECT c.game_name, c.card_value, c.code, o.id as order_id, o.purchase_date, o.status, r.id as review_id, r.rating as review_rating 
                                 FROM orders o 
                                 JOIN cards c ON o.card_id = c.id 
                                 LEFT JOIN reviews r ON o.id = r.order_id
                                 WHERE o.user_id = ? ORDER BY o.purchase_date DESC");
$stmt_purchased->execute([$user_id]);
$purchased_cards = $stmt_purchased->fetchAll();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
    <h1 style="margin: 0; font-weight: 800; background: linear-gradient(90deg, var(--primary-color), var(--secondary-color)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; text-shadow: 0 0 15px rgba(187, 134, 252, 0.2);">User Dashboard</h1>
    <a href="shop.php" class="btn btn-secondary" style="color: #000; border-radius: 6px; padding: 10px 20px;">Browse Shop &rarr;</a>
</div>

<!-- Glow Widgets Row -->
<div class="card-grid" style="grid-template-columns: repeat(auto-fit, minmax(290px, 1fr)); gap: 20px; margin-bottom: 40px;">
    <!-- Wallet Balance Widget -->
    <div class="glass-container" style="border: 1px solid rgba(3, 218, 198, 0.2); border-radius: 12px; padding: 20px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 4px 20px rgba(3, 218, 198, 0.05); position: relative; overflow: hidden;">
        <div>
            <h3 style="color: var(--secondary-color); margin: 0 0 5px 0; font-size: 0.95rem; text-transform: uppercase; letter-spacing: 1px;">Wallet Balance</h3>
            <div style="font-size: 2.2rem; font-weight: 800; color: #fff; text-shadow: 0 0 10px rgba(3, 218, 198, 0.2);"><?php echo htmlspecialchars(currencyFormat($pdo, $wallet_balance)); ?></div>
        </div>
        <button class="btn btn-secondary" onclick="openDepositModal()" style="color: #000; padding: 8px 16px; border-radius: 6px; font-weight: 600; box-shadow: 0 4px 10px rgba(3, 218, 198, 0.2); font-size: 0.9rem;">+ Add Funds</button>
    </div>

    <!-- Login Token Widget -->
    <div class="glass-container" style="border: 1px solid rgba(187, 134, 252, 0.2); border-radius: 12px; padding: 20px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 4px 20px rgba(187, 134, 252, 0.05); position: relative; overflow: hidden;">
        <div>
            <h3 style="color: var(--primary-color); margin: 0 0 5px 0; font-size: 0.95rem; text-transform: uppercase; letter-spacing: 1px;">Secure Access Token</h3>
            <div style="font-family: monospace; font-size: 0.9rem; color: #ccc; background: rgba(0,0,0,0.3); padding: 6px 12px; border-radius: 6px; border: 1px solid #333; letter-spacing: 1px;" id="tokenDisplay"><?php echo htmlspecialchars(substr($login_token, 0, 14)) . "..."; ?></div>
        </div>
        <button class="btn" onclick="copyLoginToken('<?php echo htmlspecialchars($login_token); ?>')" id="copyTokenBtn" style="padding: 8px 16px; border-radius: 6px; font-weight: 600; box-shadow: 0 4px 10px rgba(187, 134, 252, 0.2); font-size: 0.9rem;">Copy Token</button>
    </div>
</div>

<?php
if (isset($_SESSION['buy_success'])) {
    echo "<div class='alert alert-success'>" . htmlspecialchars($_SESSION['buy_success']) . "</div>";
    unset($_SESSION['buy_success']);
}
if (isset($_SESSION['buy_error'])) {
    echo "<div class='alert alert-error'>" . htmlspecialchars($_SESSION['buy_error']) . "</div>";
    unset($_SESSION['buy_error']);
}
if (isset($_SESSION['review_success'])) {
    echo "<div class='alert alert-success'>" . htmlspecialchars($_SESSION['review_success']) . "</div>";
    unset($_SESSION['review_success']);
}
if (isset($_SESSION['review_error'])) {
    echo "<div class='alert alert-error'>" . htmlspecialchars($_SESSION['review_error']) . "</div>";
    unset($_SESSION['review_error']);
}
if (isset($_SESSION['deposit_success'])) {
    echo "<div class='alert alert-success'>" . htmlspecialchars($_SESSION['deposit_success']) . "</div>";
    unset($_SESSION['deposit_success']);
}
if (isset($_SESSION['deposit_error'])) {
    echo "<div class='alert alert-error'>" . htmlspecialchars($_SESSION['deposit_error']) . "</div>";
    unset($_SESSION['deposit_error']);
}
?>

<div style="margin-top: 30px;">
    <h2>My Purchased Cards</h2>
    <?php if (empty($purchased_cards)): ?>
        <div style="background-color: var(--card-bg); border: 1px dashed #444; padding: 40px; border-radius: 12px; text-align: center; margin-top: 15px;">
            <p style="color: #888; font-size: 1.1rem; margin-bottom: 20px;">You haven't simulated any card purchases yet.</p>
            <a href="shop.php" class="btn">Explore Card Inventory</a>
        </div>
    <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Game Name</th>
                        <th>Value</th>
                        <th>Status</th>
                        <th>Purchase Date</th>
                        <th>Card Details</th>
                        <th>Feedback</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($purchased_cards as $p_card): ?>
                        <tr>
                            <td style="font-weight: 600; color: #fff;"><?php echo htmlspecialchars($p_card['game_name']); ?></td>
                            <td><?php echo htmlspecialchars($p_card['card_value']); ?></td>
                            <td>
                                <?php if ($p_card['status'] === 'verified'): ?>
                                    <span style="color: var(--secondary-color); font-weight: bold; background-color: rgba(3, 218, 198, 0.1); padding: 3px 8px; border-radius: 4px; border: 1px solid rgba(3, 218, 198, 0.2);">Verified</span>
                                <?php elseif ($p_card['status'] === 'rejected'): ?>
                                    <span style="color: var(--danger); font-weight: bold; background-color: rgba(207, 102, 121, 0.1); padding: 3px 8px; border-radius: 4px; border: 1px solid rgba(207, 102, 121, 0.2);">Rejected</span>
                                <?php else: ?>
                                    <span style="color: orange; font-weight: bold; background-color: rgba(255, 165, 0, 0.1); padding: 3px 8px; border-radius: 4px; border: 1px solid rgba(255, 165, 0, 0.2);">Pending Review</span>
                                <?php endif; ?>
                            </td>
                            <td style="color: #aaa;"><?php echo date('M d, Y H:i', strtotime($p_card['purchase_date'])); ?></td>
                            <td>
                                <?php if ($p_card['status'] === 'verified'): ?>
                                    <button class="btn btn-secondary"
                                        onclick="showSecret('<?php echo htmlspecialchars(addslashes($p_card['code'])); ?>')"
                                        style="padding: 5px 12px; font-size: 0.85rem; color: #000; border-radius: 4px;">Show Secret Code</button>
                                <?php elseif ($p_card['status'] === 'rejected'): ?>
                                    <span style="color: #666; font-size: 0.9rem; font-style: italic;">Purchase Rejected</span>
                                <?php else: ?>
                                    <span style="color: #666; font-size: 0.9rem; font-style: italic;">Awaiting Verification</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($p_card['status'] === 'verified'): ?>
                                    <?php if ($p_card['review_id'] !== null): ?>
                                        <span style="color: #ffb703; font-weight: bold; font-size: 0.95rem;">
                                            ★ <?php echo $p_card['review_rating']; ?> Reviewed
                                        </span>
                                    <?php else: ?>
                                        <button class="btn"
                                            onclick="openReviewModal('<?php echo $p_card['order_id']; ?>', '<?php echo htmlspecialchars(addslashes($p_card['game_name'] . ' - ' . $p_card['card_value'])); ?>')"
                                            style="padding: 5px 12px; font-size: 0.85rem; border-radius: 4px; box-shadow: 0 2px 5px rgba(187, 134, 252, 0.1);">Write Review</button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color: #666; font-size: 0.9rem;">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Modal Background: Card Secret Details -->
<div id="secretModal" class="modal">
    <div class="modal-content glass-container" style="border: 1px solid var(--secondary-color);">
        <span class="close-modal" onclick="closeSecret()">&times;</span>
        <h2 style="margin-bottom: 20px; color: var(--secondary-color); font-weight: 700;">Secret Code</h2>
        <div style="background-color: #111; padding: 25px; border-radius: 8px; border: 1px dashed var(--secondary-color); margin-bottom: 10px; box-shadow: inset 0 0 10px rgba(0,0,0,0.8);">
            <p style="color: #888; margin-bottom: 10px; font-size: 0.9rem;">Use the following credentials to redeem your card voucher:</p>
            <p id="secretCodeDisplay" style="font-family: monospace; font-size: 1.6rem; color: var(--secondary-color); letter-spacing: 2px; text-align: center; font-weight: bold; word-break: break-all; text-shadow: 0 0 10px rgba(3, 218, 198, 0.4);">
                <!-- Code goes here -->
            </p>
        </div>
    </div>
</div>

<!-- Modal Background: Write Review -->
<div id="reviewModal" class="modal">
    <div class="modal-content glass-container" style="border: 1px solid var(--primary-color); max-width: 450px;">
        <span class="close-modal" onclick="closeReviewModal()">&times;</span>
        <h2 style="margin-bottom: 15px; color: var(--primary-color); font-weight: 700;">Write Review</h2>
        <p style="color: #aaa; margin-bottom: 20px; font-size: 0.95rem; line-height: 1.4;">
            Product: <strong id="review_game_title" style="color: #fff;">Game Card</strong>
        </p>
        
        <form action="process_action.php?action=add_review" method="POST">
            <input type="hidden" name="order_id" id="review_order_id">
            <input type="hidden" name="game_name" id="review_game_name">
            
            <div class="form-group" style="margin-bottom: 20px;">
                <label style="color: #ccc; font-weight: 500; display: block; margin-bottom: 5px;">Your Star Rating</label>
                <div class="star-rating-form">
                    <input type="radio" id="star5" name="rating" value="5" required><label for="star5" title="5 stars">★</label>
                    <input type="radio" id="star4" name="rating" value="4"><label for="star4" title="4 stars">★</label>
                    <input type="radio" id="star3" name="rating" value="3"><label for="star3" title="3 stars">★</label>
                    <input type="radio" id="star2" name="rating" value="2"><label for="star2" title="2 stars">★</label>
                    <input type="radio" id="star1" name="rating" value="1"><label for="star1" title="1 star">★</label>
                </div>
            </div>
            
            <div class="form-group" style="margin-bottom: 20px;">
                <label style="color: #ccc; font-weight: 500; display: block; margin-bottom: 8px;">Write Feedback</label>
                <textarea name="comment" rows="4" style="width: 100%; padding: 12px; background-color: #1a1a1a; border: 1px solid #444; color: white; border-radius: 6px; resize: none; font-size: 0.95rem;" placeholder="Share your experience with this game card..." required></textarea>
            </div>
            
            <button type="submit" class="btn" style="width: 100%; border-radius: 6px; font-size: 1.05rem; padding: 12px; box-shadow: 0 4px 15px rgba(187, 134, 252, 0.2);">Submit Star Rating</button>
        </form>
    </div>
</div>

<!-- Modal Background: Add Funds to Wallet -->
<div id="depositModal" class="modal">
    <div class="modal-content glass-container" style="border: 1px solid var(--secondary-color); max-width: 500px; padding: 30px; border-radius: 12px;">
        <span class="close-modal" onclick="closeDepositModal()">&times;</span>
        <h2 style="margin-bottom: 10px; color: var(--secondary-color); font-weight: 700;">Deposit Funds</h2>
        <p style="color: #aaa; font-size: 0.95rem; margin-bottom: 25px;">Load balance into your account wallet via Simulated Card or Cryptocurrency.</p>
        
        <form action="process_action.php?action=deposit_funds" method="POST" id="depositForm">
            <!-- Amount Input -->
            <div class="form-group" style="margin-bottom: 20px;">
                <label style="color: #ccc; font-weight: 500; display: block; margin-bottom: 8px;">Amount to Deposit (<?php echo htmlspecialchars($display_currency); ?>)</label>
                <div style="position: relative;">
                    <span style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--secondary-color); font-size: 1.2rem; font-weight: bold;"><?php echo htmlspecialchars($display_symbol); ?></span>
                    <input type="number" name="amount" min="<?php echo htmlspecialchars(number_format($deposit_min_display, 2, '.', '')); ?>" max="<?php echo htmlspecialchars(number_format($deposit_max_display, 2, '.', '')); ?>" step="0.01" value="<?php echo htmlspecialchars(number_format($deposit_default_display, 2, '.', '')); ?>" style="width: 100%; padding: 12px 12px 12px 30px; background-color: #1a1a1a; border: 1px solid #444; color: white; border-radius: 6px; font-size: 1.2rem; font-weight: bold; color: var(--secondary-color);" required oninput="calculateCryptoDepositTotal()">
                    <input type="hidden" name="amount_currency" value="<?php echo htmlspecialchars($display_currency); ?>">
                </div>
            </div>

            <!-- Payment Method selector tabs in Modal -->
            <div style="display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 1px solid #333; padding-bottom: 15px; flex-wrap: wrap;">
                <button type="button" class="btn btn-secondary" id="depTabCard" onclick="switchDepositTab('card')" style="flex: 1; font-size: 0.9rem; padding: 10px; border-radius: 6px; box-shadow: none;">💳 Simulated Card</button>
                <button type="button" class="btn" id="depTabPaymentApp" onclick="switchDepositTab('payment_app')" style="flex: 1; font-size: 0.9rem; padding: 10px; border-radius: 6px; box-shadow: none; background: #222; color: #aaa; border: 1px solid #444;">📲 DarkPay App</button>
                <button type="button" class="btn" id="depTabCrypto" onclick="switchDepositTab('crypto')" style="flex: 1; font-size: 0.9rem; padding: 10px; border-radius: 6px; box-shadow: none; background: #222; color: #aaa; border: 1px solid #444;">🪙 Crypto Coins</button>
            </div>
            
            <input type="hidden" name="payment_method" id="deposit_payment_method" value="credit_card">

            <!-- DarkPay Portal Panel -->
            <div id="depositPaymentAppPanel" style="display: none;">
                <div class="glass-container" style="border: 1px solid rgba(187, 134, 252, 0.2); border-radius: 8px; padding: 20px; background: rgba(0,0,0,0.3); margin-bottom: 20px; text-align: center;">
                    <h3 style="color: var(--primary-color); margin: 0 0 10px 0; font-size: 1.1rem; text-transform: uppercase; letter-spacing: 1px;">📲 DarkPay Gateway</h3>
                    <p style="color: #ccc; font-size: 0.95rem; line-height: 1.5; margin: 0;">
                        You will be securely redirected to the external **DarkPay Gateway** to authorize your wallet deposit instantly via simulated cards, UPI or Netbanking.
                    </p>
                </div>
            </div>

            <!-- Card inputs panel -->
            <div id="depositCardPanel">
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="color: #ccc; font-size: 0.9rem; display: block; margin-bottom: 5px;">Card Holder Name</label>
                    <input type="text" name="card_name" placeholder="John Doe" style="width: 100%; padding: 10px; background-color: #1a1a1a; border: 1px solid #444; color: white; border-radius: 6px;" id="depCardName">
                </div>
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="color: #ccc; font-size: 0.9rem; display: block; margin-bottom: 5px;">Simulated Card Number</label>
                    <input type="text" name="card_number" placeholder="4111 2222 3333 4444" style="width: 100%; padding: 10px; background-color: #1a1a1a; border: 1px solid #444; color: white; border-radius: 6px;" id="depCardNumber">
                </div>
            </div>

            <!-- Crypto inputs panel -->
            <div id="depositCryptoPanel" style="display: none;">
                <!-- Coin Selector Grid -->
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-bottom: 20px;">
                    <?php require_once 'includes/crypto_helper.php';
                    foreach (CRYPTO_ADDRESSES as $coin_key => $addr):
                        $clean_lbl = str_replace(['USDT ', ' (', ')'], '', CRYPTO_NAMES[$coin_key]);
                    ?>
                        <button type="button" class="coin-btn" id="depCoin_<?php echo $coin_key; ?>" 
                            onclick="selectDepositCoin('<?php echo $coin_key; ?>', '<?php echo $addr; ?>')"
                            style="padding: 6px 4px; font-size: 0.75rem; border: 1px solid #333; background: #1a1a1a; color: #aaa; border-radius: 6px; cursor: pointer; transition: all 0.3s; font-weight: bold; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                            <?php echo $clean_lbl; ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <!-- Recipient address and converted amount display -->
                <div class="glass-container" style="border: 1px solid rgba(187, 134, 252, 0.2); border-radius: 8px; padding: 15px; margin-bottom: 20px; background-color: rgba(0,0,0,0.3);">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 0.85rem;">
                        <span style="color: #888;">Expected Crypto Total:</span>
                        <span id="depositCryptoTotalDisplay" style="color: var(--secondary-color); font-weight: bold;">--</span>
                    </div>
                    
                    <label style="color: #888; font-size: 0.85rem; display: block; margin-bottom: 4px;">Send precisely to this Wallet:</label>
                    <div style="display: flex; gap: 8px;">
                        <input type="text" id="depositWalletInput" readonly style="flex: 1; padding: 8px; background: #111; border: 1px solid #333; border-radius: 6px; color: #fff; font-family: monospace; font-size: 0.8rem;" value="">
                        <button type="button" class="btn btn-secondary" onclick="copyDepositWallet()" style="padding: 6px 12px; font-size: 0.8rem; color: #000; border-radius: 4px;">Copy</button>
                    </div>
                </div>

                <!-- TXID Hash Input -->
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="color: #ccc; font-size: 0.9rem; display: block; margin-bottom: 5px; font-weight: 600;">Transaction Hash (TXID)</label>
                    <input type="text" name="txid" id="depositTxidInput" placeholder="Paste your transfer transaction signature..." style="width: 100%; padding: 12px; background-color: #1a1a1a; border: 1px solid var(--primary-color); color: white; border-radius: 6px; font-family: monospace;">
                    <p style="color: #777; font-size: 0.75rem; margin-top: 5px;">Enter <strong>test</strong> or <strong>demo</strong> to verify instantly in sandbox!</p>
                </div>
            </div>

            <button type="submit" class="btn btn-secondary" style="width: 100%; border-radius: 6px; font-size: 1.05rem; padding: 12px; color: #000; font-weight: bold; box-shadow: 0 4px 15px rgba(3, 218, 198, 0.2); margin-top: 10px;">Proceed with Deposit</button>
        </form>
    </div>
</div>

<script>
    // Access Token Copy Function
    function copyLoginToken(token) {
        navigator.clipboard.writeText(token).then(() => {
            const btn = document.getElementById('copyTokenBtn');
            const originalText = btn.innerText;
            btn.innerText = "Copied!";
            btn.style.background = "var(--secondary-color)";
            btn.style.color = "#000";
            setTimeout(() => {
                btn.innerText = originalText;
                btn.style.background = "";
                btn.style.color = "";
            }, 2000);
        }).catch(err => {
            alert('Failed to copy access token. Token is: ' + token);
        });
    }

    // Wallet Deposit Modal Functions
    function openDepositModal() {
        document.getElementById('depositModal').style.display = "flex";
    }

    function closeDepositModal() {
        document.getElementById('depositModal').style.display = "none";
    }

    let activeCoin = '';
    
    function switchDepositTab(tab) {
        const tabCard = document.getElementById('depTabCard');
        const tabPaymentApp = document.getElementById('depTabPaymentApp');
        const tabCrypto = document.getElementById('depTabCrypto');
        
        const panelCard = document.getElementById('depositCardPanel');
        const panelPaymentApp = document.getElementById('depositPaymentAppPanel');
        const panelCrypto = document.getElementById('depositCryptoPanel');
        
        const paymentInput = document.getElementById('deposit_payment_method');
        
        const resetTab = (btn) => {
            if (!btn) return;
            btn.className = "btn";
            btn.style.background = "#222";
            btn.style.color = "#aaa";
            btn.style.border = "1px solid #444";
        };
        
        const setTabActive = (btn) => {
            if (!btn) return;
            btn.className = "btn btn-secondary";
            btn.style.background = "";
            btn.style.color = "#000";
            btn.style.border = "";
        };

        resetTab(tabCard);
        resetTab(tabPaymentApp);
        resetTab(tabCrypto);
        
        if (panelCard) panelCard.style.display = "none";
        if (panelPaymentApp) panelPaymentApp.style.display = "none";
        if (panelCrypto) panelCrypto.style.display = "none";
        
        if (document.getElementById('depCardName')) document.getElementById('depCardName').required = false;
        if (document.getElementById('depCardNumber')) document.getElementById('depCardNumber').required = false;
        if (document.getElementById('depositTxidInput')) document.getElementById('depositTxidInput').required = false;

        if (tab === 'card') {
            setTabActive(tabCard);
            if (panelCard) panelCard.style.display = "block";
            paymentInput.value = "credit_card";
            if (document.getElementById('depCardName')) document.getElementById('depCardName').required = true;
            if (document.getElementById('depCardNumber')) document.getElementById('depCardNumber').required = true;
        } else if (tab === 'payment_app') {
            setTabActive(tabPaymentApp);
            if (panelPaymentApp) panelPaymentApp.style.display = "block";
            paymentInput.value = "payment_app";
        } else if (tab === 'crypto') {
            setTabActive(tabCrypto);
            if (panelCrypto) panelCrypto.style.display = "block";
            paymentInput.value = activeCoin ? activeCoin : "usdt_bep20";
            if (document.getElementById('depositTxidInput')) document.getElementById('depositTxidInput').required = true;
            if (!activeCoin) {
                selectDepositCoin('usdt_bep20', '<?php echo CRYPTO_ADDRESSES['usdt_bep20']; ?>');
            }
        }
    }

    function selectDepositCoin(coin, address) {
        activeCoin = coin;
        document.getElementById('deposit_payment_method').value = coin;
        document.getElementById('depositWalletInput').value = address;
        
        // Remove active class from all coin buttons
        document.querySelectorAll('.coin-btn').forEach(btn => {
            btn.style.border = "1px solid #333";
            btn.style.background = "#1a1a1a";
            btn.style.color = "#aaa";
        });
        
        // Add active style to selected coin button
        const activeBtn = document.getElementById('depCoin_' + coin);
        if (activeBtn) {
            activeBtn.style.border = "1px solid var(--secondary-color)";
            activeBtn.style.background = "rgba(3, 218, 198, 0.1)";
            activeBtn.style.color = "var(--secondary-color)";
        }
        
        calculateCryptoDepositTotal();
    }

    function calculateCryptoDepositTotal() {
        const usdAmount = parseFloat(document.querySelector('input[name="amount"]').value);
        if (isNaN(usdAmount) || usdAmount <= 0 || !activeCoin) {
            document.getElementById('depositCryptoTotalDisplay').innerText = '--';
            return;
        }
        
        if (activeCoin === 'usdt_bep20' || activeCoin === 'usdt_erc20') {
            document.getElementById('depositCryptoTotalDisplay').innerText = usdAmount.toFixed(2) + " USDT";
            return;
        }
        
        let symbol = "";
        let coinName = "";
        switch (activeCoin) {
            case 'btc': symbol = "BTCUSDT"; coinName = "BTC"; break;
            case 'ltc': symbol = "LTCUSDT"; coinName = "LTC"; break;
            case 'eth': symbol = "ETHUSDT"; coinName = "ETH"; break;
            case 'sol': symbol = "SOLUSDT"; coinName = "SOL"; break;
            case 'pol': symbol = "MATICUSDT"; coinName = "POL"; break;
            case 'trx': symbol = "TRXUSDT"; coinName = "TRX"; break;
        }
        
        document.getElementById('depositCryptoTotalDisplay').innerText = "Fetching conversion rate...";
        
        fetch(`https://api.binance.com/api/v3/ticker/price?symbol=${symbol}`)
            .then(res => res.json())
            .then(data => {
                if (data && data.price) {
                    const rate = parseFloat(data.price);
                    const cryptoVal = usdAmount / rate;
                    const decimals = (coinName === 'BTC' || coinName === 'ETH') ? 6 : 4;
                    document.getElementById('depositCryptoTotalDisplay').innerText = `${cryptoVal.toFixed(decimals)} ${coinName} (@ $${rate.toLocaleString()})`;
                } else {
                    document.getElementById('depositCryptoTotalDisplay').innerText = '--';
                }
            })
            .catch(() => {
                document.getElementById('depositCryptoTotalDisplay').innerText = 'Rate API down';
            });
    }

    function copyDepositWallet() {
        const wallet = document.getElementById('depositWalletInput').value;
        if (!wallet) return;
        navigator.clipboard.writeText(wallet).then(() => {
            alert('Receiving wallet address successfully copied to clipboard!');
        });
    }

    // Secret Details Modal Functions
    function showSecret(code) {
        document.getElementById('secretCodeDisplay').innerText = code;
        document.getElementById('secretModal').style.display = "flex";
    }

    function closeSecret() {
        document.getElementById('secretModal').style.display = "none";
    }

    // Review Modal Functions
    function openReviewModal(orderId, gameName) {
        document.getElementById('review_order_id').value = orderId;
        document.getElementById('review_game_name').value = gameName;
        document.getElementById('review_game_title').innerText = gameName;
        document.getElementById('reviewModal').style.display = "flex";
    }

    function closeReviewModal() {
        document.getElementById('reviewModal').style.display = "none";
    }

    // Close when clicking outside of modal boxes
    window.onclick = function (event) {
        let secModal = document.getElementById('secretModal');
        let revModal = document.getElementById('reviewModal');
        let depModal = document.getElementById('depositModal');
        if (event.target == secModal) {
            closeSecret();
        }
        if (event.target == revModal) {
            closeReviewModal();
        }
        if (event.target == depModal) {
            closeDepositModal();
        }
    }
</script>

<?php require_once 'includes/footer.php'; ?>
