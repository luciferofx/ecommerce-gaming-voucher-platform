<?php
// checkout.php
require_once 'includes/db.php';
require_once 'includes/crypto_helper.php';
require_once 'includes/currency.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['card_id'])) {
    header("Location: dashboard.php");
    exit();
}

$card_id = intval($_GET['card_id']);

// Fetch card details
$stmt = $pdo->prepare("SELECT * FROM cards WHERE id = ? AND status = 'available'");
$stmt->execute([$card_id]);
$card = $stmt->fetch();

if (!$card) {
    $_SESSION['buy_error'] = "Sorry, this card is no longer available.";
    header("Location: dashboard.php");
    exit();
}

// Fetch available stock of this card type and value
$stmt_stock = $pdo->prepare("SELECT COUNT(*) FROM cards WHERE game_name = ? AND card_value = ? AND status = 'available'");
$stmt_stock->execute([$card['game_name'], $card['card_value']]);
$available_stock = intval($stmt_stock->fetchColumn());

$user_id = $_SESSION['user_id'];
$stmt_balance = $pdo->prepare("SELECT wallet_balance FROM users WHERE id = ?");
$stmt_balance->execute([$user_id]);
$wallet_balance = floatval($stmt_balance->fetchColumn());
$display_currency = currencyDisplayCode($pdo);
$display_symbols = ['USD' => '$', 'EUR' => '€', 'INR' => '₹'];
$display_symbol = $display_symbols[$display_currency] ?? '$';
$display_unit_price = currencyConvertFromUsd($pdo, $card['price'], $display_currency);

require_once 'includes/header.php';
?>

<div style="max-width: 900px; margin: 0 auto; padding: 0 10px;">
    <h1 style="margin-bottom: 25px; font-weight: 800; background: linear-gradient(90deg, var(--primary-color), var(--secondary-color)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; text-shadow: 0 0 15px rgba(187, 134, 252, 0.2);">Secure Checkout</h1>
    
    <?php
    if (isset($_SESSION['buy_error'])) {
        echo "<div class='alert alert-error' style='margin-bottom: 20px;'>" . htmlspecialchars($_SESSION['buy_error']) . "</div>";
        unset($_SESSION['buy_error']);
    }
    ?>

    <div class="card-grid check-grid" style="gap: 30px; align-items: start;">
        <!-- Card Summary Showcase -->
        <div class="game-card glass-container checkout-summary-card" style="margin: 0; padding: 30px; border: 1px solid rgba(187, 134, 252, 0.15); box-shadow: 0 4px 25px rgba(187, 134, 252, 0.05); text-align: center; border-radius: 12px;">
            <span style="font-size: 0.8rem; background-color: rgba(187,134,252,0.1); color: var(--primary-color); padding: 5px 12px; border-radius: 4px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">Selected Product</span>
            <h2 style="margin-top: 15px; margin-bottom: 5px; font-size: 1.8rem; font-weight: 700; color: #fff; text-shadow: 0 0 10px rgba(187, 134, 252, 0.2);"><?php echo htmlspecialchars($card['game_name']); ?></h2>
            <p style="color: #aaa; font-size: 1.05rem; margin-bottom: 20px;">Value: <?php echo htmlspecialchars($card['card_value']); ?></p>
            
            <div style="border-top: 1px solid #333; border-bottom: 1px solid #333; padding: 15px 0; margin-bottom: 25px;">
                <span style="color: #888; font-size: 0.9rem; display: block; margin-bottom: 5px;">Total Price:</span>
                <span id="total_pay_display" style="color: var(--secondary-color); font-size: 2.2rem; font-weight: 800; text-shadow: 0 0 10px rgba(3, 218, 198, 0.2);"><?php echo htmlspecialchars(currencyFormat($pdo, $card['price'])); ?></span>
            </div>

            <!-- Real-time Binance Price Ticker Display inside Summary -->
            <div id="crypto_conversion_display" style="display: none; background: rgba(0,0,0,0.4); padding: 15px; border-radius: 8px; border: 1px dashed var(--secondary-color);">
                <div style="color: #888; font-size: 0.8rem; text-transform: uppercase; margin-bottom: 4px;">Coin Quantity:</div>
                <div id="crypto_converted_amount" style="font-size: 1.4rem; font-weight: bold; color: var(--secondary-color); margin-bottom: 6px;">Converting...</div>
                <div id="crypto_live_rate_info" style="color: #888; font-size: 0.75rem; font-family: monospace;">Binance Ticker Connecting...</div>
            </div>
        </div>

        <!-- Checkout Form Gateway Selector -->
        <div class="form-container glass-container" style="margin: 0; max-width: 100%; border: 1px solid #333; border-radius: 12px;">
            <h2 style="margin-bottom: 25px; font-size: 1.4rem; color: #fff;">Choose Payment Gateway</h2>
            
            <!-- Gateway Tabs -->
            <div style="display: flex; gap: 8px; margin-bottom: 25px; flex-wrap: wrap;">
                <button type="button" id="tab_card" onclick="selectGateway('card')" style="flex: 1; min-width: 130px; padding: 12px 8px; border-radius: 6px; border: none; font-weight: bold; font-size: 0.9rem; cursor: pointer; transition: all 0.3s; background-color: var(--primary-color); color: #fff;">💳 Card Simulation</button>
                <button type="button" id="tab_payment_app" onclick="selectGateway('payment_app')" style="flex: 1; min-width: 130px; padding: 12px 8px; border-radius: 6px; border: none; font-weight: bold; font-size: 0.9rem; cursor: pointer; transition: all 0.3s; background-color: #333; color: #aaa;">📲 DarkPay App</button>
                <button type="button" id="tab_crypto" onclick="selectGateway('crypto')" style="flex: 1; min-width: 130px; padding: 12px 8px; border-radius: 6px; border: none; font-weight: bold; font-size: 0.9rem; cursor: pointer; transition: all 0.3s; background-color: #333; color: #aaa;">🪙 Cryptocurrency</button>
                <button type="button" id="tab_wallet" onclick="selectGateway('wallet')" style="flex: 1; min-width: 130px; padding: 12px 8px; border-radius: 6px; border: none; font-weight: bold; font-size: 0.9rem; cursor: pointer; transition: all 0.3s; background-color: #333; color: #aaa;">👛 Wallet (<?php echo htmlspecialchars(currencyFormat($pdo, $wallet_balance)); ?>)</button>
            </div>

            <form action="process_action.php?action=checkout" method="POST" id="checkout_form">
                <input type="hidden" name="card_id" value="<?php echo $card['id']; ?>">
                <input type="hidden" name="payment_method" id="selected_method" value="credit_card">
                
                <!-- Shared Quantity Selector -->
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="color: #ccc; font-weight: 500;">Quantity (Available Stock: <strong><?php echo $available_stock; ?></strong>)</label>
                    <input type="number" name="quantity" id="quantity_input" min="1" max="<?php echo $available_stock; ?>" value="1" style="background-color: #1a1a1a; border: 1px solid #444; border-radius: 6px; padding: 12px; width: 100%; color: white; font-weight: bold;" required>
                </div>

                <!-- DARKPAY PAYMENT APP CONTENT -->
                <div id="section_payment_app" style="display: none;">
                    <div class="glass-container" style="border: 1px solid rgba(187, 134, 252, 0.2); border-radius: 8px; padding: 20px; background: rgba(0,0,0,0.3); margin-bottom: 20px; text-align: center;">
                        <h3 style="color: var(--primary-color); margin: 0 0 10px 0; font-size: 1.1rem; text-transform: uppercase; letter-spacing: 1px;">📲 DarkPay Gateway</h3>
                        <p style="color: #ccc; font-size: 0.95rem; line-height: 1.5; margin-bottom: 0;">
                            You will be securely redirected to the external **DarkPay Gateway** application to authorize your simulated payment instantly.
                        </p>
                    </div>
                </div>

                <!-- CARD PAYMENT CONTENT -->
                <div id="section_card" style="display: block;">
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label style="color: #ccc; font-weight: 500;">Card Number / Details</label>
                        <input type="text" name="payment_details" id="card_details_input" placeholder="Simulated card details..." style="background-color: #1a1a1a; border: 1px solid #444; border-radius: 6px; padding: 12px;" required>
                    </div>

                    <div class="form-group" style="display: flex; gap: 15px; margin-bottom: 25px;">
                        <div style="flex: 1;">
                            <label style="color: #ccc; font-weight: 500;">Expiry Date</label>
                            <input type="text" placeholder="MM/YY" style="background-color: #1a1a1a; border: 1px solid #444; border-radius: 6px; padding: 12px; text-align: center;">
                        </div>
                        <div style="flex: 1;">
                            <label style="color: #ccc; font-weight: 500;">CVV Code</label>
                            <input type="password" placeholder="123" maxlength="4" style="background-color: #1a1a1a; border: 1px solid #444; border-radius: 6px; padding: 12px; text-align: center;">
                        </div>
                    </div>
                </div>

                <!-- CRYPTO PAYMENT CONTENT -->
                <div id="section_crypto" style="display: none;">
                    <p style="color: #aaa; font-size: 0.9rem; margin-bottom: 15px; line-height: 1.4;">Select a cryptocurrency coin below to pay and obtain the transfer wallet address:</p>
                    
                    <!-- Crypto Coins List Grid -->
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-bottom: 25px;">
                        <?php foreach (CRYPTO_NAMES as $key => $name): ?>
                            <button type="button" class="coin-btn" id="coin_<?php echo $key; ?>" 
                                    onclick="selectCoin('<?php echo $key; ?>', '<?php echo CRYPTO_ADDRESSES[$key]; ?>')" 
                                    style="padding: 10px; border-radius: 6px; border: 1px solid #444; background-color: #1a1a1a; color: #ccc; cursor: pointer; text-align: center; font-weight: 500; font-size: 0.85rem; transition: all 0.3s; display: flex; align-items: center; justify-content: center; gap: 6px;">
                                <span style="font-size: 1rem;">🪙</span> <?php echo $name; ?>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <!-- Selected Coin Details Box -->
                    <div id="coin_details_box" style="display: none; background-color: #1a1a1a; border: 1px solid #444; border-radius: 8px; padding: 20px; margin-bottom: 25px;">
                        <!-- QR Code and Address Layout -->
                        <div style="display: flex; gap: 20px; align-items: center; margin-bottom: 15px; flex-wrap: wrap;">
                            <div style="background: white; padding: 5px; border-radius: 6px; display: inline-block;">
                                <img id="crypto_qr_code" src="" alt="Payment QR Code" style="width: 130px; height: 130px; display: block;">
                            </div>
                            <div style="flex: 1; min-width: 200px;">
                                <p style="color: #888; font-size: 0.8rem; text-transform: uppercase; margin-bottom: 5px; font-weight: bold;">Send Funds to Address:</p>
                                <div style="background-color: #111; padding: 10px; border-radius: 4px; border: 1px solid #333; font-family: monospace; font-size: 0.85rem; color: var(--secondary-color); word-break: break-all; margin-bottom: 10px; user-select: all;" id="crypto_address_text">
                                    --
                                </div>
                                <button type="button" class="btn btn-secondary" onclick="copyAddress()" style="color: #000; padding: 5px 12px; font-size: 0.8rem; border-radius: 4px; font-weight: bold;" id="btn_copy">Copy Address</button>
                            </div>
                        </div>
                        <p style="color: #ff9f1c; font-size: 0.8rem; line-height: 1.4; margin: 0; font-style: italic;">Note: Send only the selected network coin to this address. BEP20 stands for BNB Smart Chain, ERC20 for Ethereum network.</p>
                    </div>

                    <!-- TXID Input Details -->
                    <div class="form-group" style="margin-bottom: 25px;">
                        <label style="color: #ccc; font-weight: 600; font-size: 0.95rem;">Transaction Hash (TXID)</label>
                        <input type="text" name="txid" id="crypto_txid" placeholder="Paste your block transaction hash signature..." style="background-color: #1a1a1a; border: 1px solid var(--secondary-color); border-radius: 6px; padding: 12px; font-family: monospace;">
                        <p style="font-size: 0.8rem; color: #888; margin-top: 5px;">Our automated verification scanner will instantly check the blockchain ledger and release your card details immediately if confirmed!</p>
                    </div>
                </div>

                <!-- WALLET PAYMENT CONTENT -->
                <div id="section_wallet" style="display: none;">
                    <div class="glass-container" style="border: 1px solid rgba(3, 218, 198, 0.2); border-radius: 8px; padding: 20px; background: rgba(0,0,0,0.3); margin-bottom: 20px; text-align: center;">
                        <h3 style="color: var(--secondary-color); margin: 0 0 10px 0; font-size: 1rem;">Available Wallet Funds</h3>
                        <div style="font-size: 2.2rem; font-weight: 800; color: #fff; text-shadow: 0 0 10px rgba(3, 218, 198, 0.2); margin-bottom: 10px;"><?php echo htmlspecialchars(currencyFormat($pdo, $wallet_balance)); ?></div>
                        <div id="wallet_balance_status" style="font-size: 0.95rem; font-weight: 600;">
                            <!-- Managed by JS -->
                        </div>
                    </div>
                </div>

                <p style="font-size: 0.85rem; color: #888; margin-bottom: 20px; line-height: 1.4;">This payment page represents a fully integrated custom simulated payment sandbox. Cryptocurrency verification uses real-time explorers.</p>
                
                <button type="submit" class="btn" style="width: 100%; font-size: 1.1rem; padding: 12px; box-shadow: 0 4px 15px rgba(187, 134, 252, 0.2);">Confirm Payment & Verify</button>
                <a href="dashboard.php" style="display: block; text-align: center; margin-top: 15px; color: #aaa; text-decoration: underline;">Cancel and return</a>
            </form>
        </div>
    </div>
</div>

<script>
    // Price Ticker & Stock Quantity Logic
    const quantityInput = document.getElementById('quantity_input');
    const totalPayDisplay = document.getElementById('total_pay_display');
    const unitPrice = <?php echo floatval($card['price']); ?>;
    const displayUnitPrice = <?php echo json_encode($display_unit_price); ?>;
    const displaySymbol = <?php echo json_encode($display_symbol); ?>;
    const displayCurrency = <?php echo json_encode($display_currency); ?>;
    
    let currentGateway = 'card';
    let currentCoin = '';
    let currentAddress = '';

    const walletBalance = <?php echo $wallet_balance; ?>;

    // Handle Quantity updates
    quantityInput.addEventListener('input', function() {
        let qty = parseInt(quantityInput.value) || 1;
        let max = parseInt(quantityInput.max) || 1;
        if (qty < 1) qty = 1;
        if (qty > max) qty = max;
        quantityInput.value = qty;
        
        let total = unitPrice * qty;
        let displayTotal = displayUnitPrice * qty;
        totalPayDisplay.innerText = displaySymbol + displayTotal.toFixed(2) + ' ' + displayCurrency;
        
        // If crypto is selected, update coin total as well
        if (currentGateway === 'crypto' && currentCoin) {
            updateCryptoConversion();
        } else if (currentGateway === 'wallet') {
            checkWalletBalanceLimit();
        }
    });

    // Tab switcher between Card, Crypto and Wallet gateways
    function selectGateway(type) {
        currentGateway = type;
        
        const tabCard = document.getElementById('tab_card');
        const tabPaymentApp = document.getElementById('tab_payment_app');
        const tabCrypto = document.getElementById('tab_crypto');
        const tabWallet = document.getElementById('tab_wallet');
        
        const secCard = document.getElementById('section_card');
        const secPaymentApp = document.getElementById('section_payment_app');
        const secCrypto = document.getElementById('section_crypto');
        const secWallet = document.getElementById('section_wallet');
        
        const inputMethod = document.getElementById('selected_method');
        const inputDetails = document.getElementById('card_details_input');
        const inputTxid = document.getElementById('crypto_txid');
        const conversionBox = document.getElementById('crypto_conversion_display');

        // Reset all tabs
        tabCard.style.backgroundColor = '#333';
        tabCard.style.color = '#aaa';
        if (tabPaymentApp) {
            tabPaymentApp.style.backgroundColor = '#333';
            tabPaymentApp.style.color = '#aaa';
        }
        tabCrypto.style.backgroundColor = '#333';
        tabCrypto.style.color = '#aaa';
        tabWallet.style.backgroundColor = '#333';
        tabWallet.style.color = '#aaa';
        
        secCard.style.display = 'none';
        if (secPaymentApp) secPaymentApp.style.display = 'none';
        secCrypto.style.display = 'none';
        secWallet.style.display = 'none';
        conversionBox.style.display = 'none';
        
        if (type === 'card') {
            tabCard.style.backgroundColor = 'var(--primary-color)';
            tabCard.style.color = '#fff';
            
            secCard.style.display = 'block';
            
            inputMethod.value = 'credit_card';
            inputDetails.required = true;
            inputTxid.required = false;
        } else if (type === 'payment_app') {
            if (tabPaymentApp) {
                tabPaymentApp.style.backgroundColor = 'var(--primary-color)';
                tabPaymentApp.style.color = '#fff';
            }
            if (secPaymentApp) secPaymentApp.style.display = 'block';
            
            inputMethod.value = 'payment_app';
            inputDetails.required = false;
            inputTxid.required = false;
        } else if (type === 'crypto') {
            tabCrypto.style.backgroundColor = 'var(--secondary-color)';
            tabCrypto.style.color = '#000';
            
            secCrypto.style.display = 'block';
            conversionBox.style.display = 'block';
            
            inputMethod.value = currentCoin ? currentCoin : 'usdt_bep20';
            inputDetails.required = false;
            inputTxid.required = true;
            
            // Auto select first coin if none selected
            if (!currentCoin) {
                selectCoin('usdt_bep20', '<?php echo CRYPTO_ADDRESSES['usdt_bep20']; ?>');
            }
        } else {
            tabWallet.style.backgroundColor = 'orange';
            tabWallet.style.color = '#000';
            
            secWallet.style.display = 'block';
            
            inputMethod.value = 'wallet';
            inputDetails.required = false;
            inputTxid.required = false;
            
            checkWalletBalanceLimit();
        }
    }

    function checkWalletBalanceLimit() {
        const qty = parseInt(quantityInput.value) || 1;
        const totalUsd = unitPrice * qty;
        
        const statusBox = document.getElementById('wallet_balance_status');
        if (walletBalance >= totalUsd) {
            statusBox.style.color = 'var(--secondary-color)';
            statusBox.innerHTML = '✓ Sufficient funds check passed! Click below to confirm payment.';
        } else {
            statusBox.style.color = 'var(--danger)';
            statusBox.innerHTML = '❌ Insufficient Wallet Balance! Please add funds in your dashboard or select another payment gateway.';
        }
    }

    // Tab selector for coin choices
    function selectCoin(coin, address) {
        currentCoin = coin;
        currentAddress = address;
        
        document.getElementById('selected_method').value = coin;
        
        // Highlight chosen button and reset others
        const buttons = document.querySelectorAll('.coin-btn');
        buttons.forEach(btn => {
            btn.style.borderColor = '#444';
            btn.style.backgroundColor = '#1a1a1a';
            btn.style.color = '#ccc';
            btn.style.boxShadow = 'none';
        });
        
        const activeBtn = document.getElementById('coin_' + coin);
        if (activeBtn) {
            activeBtn.style.borderColor = 'var(--secondary-color)';
            activeBtn.style.backgroundColor = 'rgba(3, 218, 198, 0.05)';
            activeBtn.style.color = '#fff';
            activeBtn.style.boxShadow = '0 0 10px rgba(3, 218, 198, 0.2)';
        }

        // Show details block
        document.getElementById('coin_details_box').style.display = 'block';
        document.getElementById('crypto_address_text').innerText = address;
        
        // Generate QR code dynamically via public API
        const qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=160x160&color=03dac6&bgcolor=1a1a1a&data=" + encodeURIComponent(address);
        document.getElementById('crypto_qr_code').src = qrUrl;
        
        // Refresh Binance price rate conversions
        updateCryptoConversion();
    }

    // Copies current address to clipboard
    function copyAddress() {
        if (!currentAddress) return;
        navigator.clipboard.writeText(currentAddress).then(() => {
            const btnCopy = document.getElementById('btn_copy');
            btnCopy.innerText = "✓ Copied!";
            btnCopy.style.backgroundColor = "var(--secondary-color)";
            setTimeout(() => {
                btnCopy.innerText = "Copy Address";
                btnCopy.style.backgroundColor = "";
            }, 1500);
        });
    }

    // Fetches live conversion from Binance API and displays equivalent coin total
    function updateCryptoConversion() {
        const qty = parseInt(quantityInput.value) || 1;
        const totalUsd = unitPrice * qty;
        
        const displayTotal = document.getElementById('crypto_converted_amount');
        const displayRate = document.getElementById('crypto_live_rate_info');
        
        displayTotal.innerText = "Converting...";
        
        // USDT conversions are 1:1
        if (currentCoin === 'usdt_bep20' || currentCoin === 'usdt_erc20') {
            displayTotal.innerText = totalUsd.toFixed(2) + " USDT";
            displayRate.innerText = "Stablecoin peg: 1 USDT = 1.00 USD";
            return;
        }

        // Determine symbol on Binance
        let binanceSymbol = "";
        switch (currentCoin) {
            case 'btc': binanceSymbol = "BTCUSDT"; break;
            case 'ltc': binanceSymbol = "LTCUSDT"; break;
            case 'eth': binanceSymbol = "ETHUSDT"; break;
            case 'sol': binanceSymbol = "SOLUSDT"; break;
            case 'pol': binanceSymbol = "MATICUSDT"; break; // Binance lists MATIC
            case 'trx': binanceSymbol = "TRXUSDT"; break;
        }

        if (!binanceSymbol) {
            displayTotal.innerText = "--";
            return;
        }

        // Fetch Binance Ticker API
        fetch("https://api.binance.com/api/v3/ticker/price?symbol=" + binanceSymbol)
            .then(res => res.json())
            .then(data => {
                if (data.price) {
                    const price = parseFloat(data.price);
                    const coinAmount = totalUsd / price;
                    
                    // Formatting depending on coin value
                    let decDigits = 6;
                    if (currentCoin === 'trx' || currentCoin === 'pol') decDigits = 3;
                    
                    displayTotal.innerText = coinAmount.toFixed(decDigits) + " " + currentCoin.toUpperCase();
                    displayRate.innerText = "Binance Rate: 1 " + currentCoin.toUpperCase() + " = $" + price.toFixed(2) + " USD";
                } else {
                    displayTotal.innerText = "--";
                }
            })
            .catch(err => {
                // Fallback display
                displayTotal.innerText = "$ " + totalUsd.toFixed(2) + " USD equivalent";
                displayRate.innerText = "Could not load real-time rate. Please proceed.";
            });
    }

    // Intercept form submit to ensure validations
    document.getElementById('checkout_form').onsubmit = function() {
        const qty = parseInt(quantityInput.value) || 1;
        const totalUsd = unitPrice * qty;

        if (currentGateway === 'crypto') {
            const txid = document.getElementById('crypto_txid').value.trim();
            if (txid.length < 10) {
                alert("Please enter a valid Transaction Hash (TXID) before confirming cryptocurrency payments.");
                return false;
            }
        } else if (currentGateway === 'wallet') {
            if (walletBalance < totalUsd) {
                alert("Insufficient Wallet Balance to complete this purchase!");
                return false;
            }
        }
        return true;
    };
</script>

<?php require_once 'includes/footer.php'; ?>
