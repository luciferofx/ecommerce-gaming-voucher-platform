<?php
// process_action.php
require_once 'includes/db.php';
require_once 'includes/crypto_helper.php';
require_once 'includes/currency.php';
require_once '../darkpay/includes/darkpay_gateway.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Add Card (Admin Only)
    if ($action === 'add_card' && $_SESSION['role'] === 'admin') {
        $game_name = trim($_POST['game_name']);
        $card_value = trim($_POST['card_value']);
        $price = floatval($_POST['price']);
        $code = trim($_POST['code']);
        $quantity = intval($_POST['quantity'] ?? 1);
        if ($quantity < 1) $quantity = 1;

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO cards (game_name, card_value, price, code) VALUES (?, ?, ?, ?)");
            
            for ($i = 0; $i < $quantity; $i++) {
                // First card gets the exact code; subsequent cards get unique random suffixes to maintain DB constraints
                $current_code = ($i === 0) ? $code : $code . "-" . strtoupper(bin2hex(random_bytes(3)));
                $stmt->execute([$game_name, $card_value, $price, $current_code]);
            }
            
            $pdo->commit();
            $_SESSION['add_success'] = "Successfully stocked $quantity card(s) in inventory!";
        } catch(PDOException $e) {
            $pdo->rollBack();
            if ($e->getCode() == 23000) {
                $_SESSION['add_error'] = "Failed: The card code or one of its variants already exists in the database.";
            } else {
                $_SESSION['add_error'] = "Error adding cards: " . $e->getMessage();
            }
        }
        header("Location: admin.php");
        exit();
    }
    
    // Create User Account (Admin Only)
    elseif ($action === 'create_user' && $_SESSION['role'] === 'admin') {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        $role = trim($_POST['role'] ?? 'user');
        $wallet_balance = floatval($_POST['wallet_balance'] ?? 0.00);

        if (strlen($username) < 3 || strlen($password) < 5) {
            $_SESSION['add_error'] = "Username must be > 3 chars and password > 5 chars.";
            header("Location: admin.php");
            exit();
        }

        // Check if username exists
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt_check->execute([$username]);
        if ($stmt_check->fetchColumn() > 0) {
            $_SESSION['add_error'] = "Failed: Username is already taken.";
            header("Location: admin.php");
            exit();
        }

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $login_token = "DARK-TOKEN-" . strtoupper(bin2hex(random_bytes(12)));

        try {
            $stmt = $pdo->prepare("INSERT INTO users (username, password, role, wallet_balance, login_token) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$username, $hashed_password, $role, $wallet_balance, $login_token]);
            $_SESSION['add_success'] = "Successfully created new user account: '$username'!";
        } catch(PDOException $e) {
            $_SESSION['add_error'] = "Failed to create user account: " . $e->getMessage();
        }
        header("Location: admin.php");
        exit();
    }

    // Refresh live currency rates (Admin Only)
    elseif ($action === 'refresh_currency_rates' && $_SESSION['role'] === 'admin') {
        try {
            currencyRates($pdo, true);
            $_SESSION['add_success'] = "Live currency rates refreshed successfully.";
        } catch (Exception $e) {
            $_SESSION['add_error'] = "Failed to refresh currency rates: " . $e->getMessage();
        }
        header("Location: admin.php");
        exit();
    }
    
    // Edit User Account parameters (Admin Only)
    elseif ($action === 'edit_user' && $_SESSION['role'] === 'admin') {
        $target_user_id = intval($_POST['target_user_id']);
        $wallet_balance = floatval($_POST['wallet_balance']);
        $role = trim($_POST['role']);
        $password = trim($_POST['password'] ?? '');

        try {
            $pdo->beginTransaction();
            
            if (!empty($password)) {
                if (strlen($password) < 5) {
                    $pdo->rollBack();
                    $_SESSION['add_error'] = "Password must be at least 5 characters.";
                    header("Location: admin.php");
                    exit();
                }
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET wallet_balance = ?, role = ?, password = ? WHERE id = ?");
                $stmt->execute([$wallet_balance, $role, $hashed_password, $target_user_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET wallet_balance = ?, role = ? WHERE id = ?");
                $stmt->execute([$wallet_balance, $role, $target_user_id]);
            }
            
            $pdo->commit();
            $_SESSION['add_success'] = "User account parameters updated successfully!";
        } catch(PDOException $e) {
            $pdo->rollBack();
            $_SESSION['add_error'] = "Failed to update user account: " . $e->getMessage();
        }
        header("Location: admin.php");
        exit();
    }

    // Delete User Account (Admin Only)
    elseif ($action === 'delete_user' && $_SESSION['role'] === 'admin') {
        $target_user_id = intval($_POST['target_user_id']);
        
        // Prevent self-deletion
        if ($target_user_id === intval($_SESSION['user_id'])) {
            $_SESSION['add_error'] = "You cannot delete your own active admin account!";
            header("Location: admin.php");
            exit();
        }

        try {
            $pdo->beginTransaction();
            
            // Purge reviews, deposits and orders logs to prevent constraint issues
            $stmt_not = $pdo->prepare("DELETE FROM notifications WHERE user_id = ?");
            $stmt_not->execute([$target_user_id]);

            $stmt_rev = $pdo->prepare("DELETE FROM reviews WHERE user_id = ?");
            $stmt_rev->execute([$target_user_id]);
            
            $stmt_dep = $pdo->prepare("DELETE FROM wallet_deposits WHERE user_id = ?");
            $stmt_dep->execute([$target_user_id]);
            
            $stmt_ord = $pdo->prepare("DELETE FROM orders WHERE user_id = ?");
            $stmt_ord->execute([$target_user_id]);
            
            // Delete user row
            $stmt_user = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt_user->execute([$target_user_id]);
            
            $pdo->commit();
            $_SESSION['add_success'] = "User account and all related audit trails successfully deleted!";
        } catch(PDOException $e) {
            $pdo->rollBack();
            $_SESSION['add_error'] = "Failed to delete user account: " . $e->getMessage();
        }
        header("Location: admin.php");
        exit();
    }

    // Edit Prepaid Card Secret Code (Admin Only)
    elseif ($action === 'edit_card_code' && $_SESSION['role'] === 'admin') {
        $card_id = intval($_POST['card_id']);
        $code = trim($_POST['code']);

        if (empty($code)) {
            $_SESSION['add_error'] = "Prepaid card credentials cannot be empty.";
            header("Location: admin.php");
            exit();
        }

        try {
            $stmt = $pdo->prepare("UPDATE cards SET code = ? WHERE id = ?");
            $stmt->execute([$code, $card_id]);
            $_SESSION['add_success'] = "Secret prepaid code successfully updated!";
        } catch(PDOException $e) {
            $_SESSION['add_error'] = "Failed to update prepaid credentials: " . $e->getMessage();
        }
        header("Location: admin.php");
        exit();
    }
    
    // Checkout Processing (Any user)
    elseif ($action === 'checkout') {
        darkpayEnsureSchema($pdo);
        $card_id = intval($_POST['card_id']);
        $quantity = intval($_POST['quantity'] ?? 1);
        if ($quantity < 1) $quantity = 1;
        $user_id = $_SESSION['user_id'];
        
        $payment_method = $_POST['payment_method'] ?? '';
        $payment_details = $_POST['payment_details'] ?? '';
        
        $status = 'pending';
        $txid = null;

        // Check chosen payment gateway
        if ($payment_method === 'wallet') {
            $status = 'verified';
        } elseif (array_key_exists($payment_method, CRYPTO_ADDRESSES)) {
            $txid = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['txid'] ?? '');
            if (empty($txid) || strlen($txid) < 10) {
                $_SESSION['buy_error'] = "A valid Transaction Hash (TXID) is required for cryptocurrency payments.";
                header("Location: checkout.php?card_id=$card_id");
                exit();
            }
            
            // Replay protection: Check if this base txid has been submitted already
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE txid = ? OR txid LIKE ?");
            $stmt_check->execute([$txid, $txid . '-%']);
            if ($stmt_check->fetchColumn() > 0) {
                $_SESSION['buy_error'] = "This transaction ID has already been submitted for another order.";
                header("Location: checkout.php?card_id=$card_id");
                exit();
            }

            // Calculate total USD to convert
            $stmt_c = $pdo->prepare("SELECT price FROM cards WHERE id = ?");
            $stmt_c->execute([$card_id]);
            $c_price = floatval($stmt_c->fetchColumn());
            $total_usd = $c_price * $quantity;

            // Attempt dynamic blockchain auto-verification
            if (verifyCryptoTx($txid, $payment_method, $total_usd)) {
                $status = 'verified';
                $_SESSION['success_verified_auto'] = true;
            } else {
                $_SESSION['success_verified_auto'] = false;
            }
        } elseif ($payment_method === 'payment_app') {
            $txid = "DARKPAY-" . strtoupper(bin2hex(random_bytes(10)));
            $status = 'pending';
        } else {
            $payment_method = 'credit_card';
            if (empty($payment_details)) {
                $_SESSION['buy_error'] = "Payment details are required.";
                header("Location: checkout.php?card_id=$card_id");
                exit();
            }
        }

        try {
            // Start transaction
            $pdo->beginTransaction();

            // Handle wallet balance verification & deduction safely
            if ($payment_method === 'wallet') {
                $stmt_bal = $pdo->prepare("SELECT wallet_balance FROM users WHERE id = ? FOR UPDATE");
                $stmt_bal->execute([$user_id]);
                $user_bal = floatval($stmt_bal->fetchColumn());
                
                // Fetch price
                $stmt_pr = $pdo->prepare("SELECT price FROM cards WHERE id = ?");
                $stmt_pr->execute([$card_id]);
                $c_price = floatval($stmt_pr->fetchColumn());
                $total_usd = $c_price * $quantity;
                
                if ($user_bal < $total_usd) {
                    $pdo->rollBack();
                    $_SESSION['buy_error'] = "Insufficient wallet balance to complete this purchase.";
                    header("Location: checkout.php?card_id=$card_id");
                    exit();
                }
                
                $new_balance = $user_bal - $total_usd;
                $stmt_deduct = $pdo->prepare("UPDATE users SET wallet_balance = ? WHERE id = ?");
                $stmt_deduct->execute([$new_balance, $user_id]);
            }

            // Get selected card details to know the type and value
            $stmt = $pdo->prepare("SELECT game_name, card_value, price FROM cards WHERE id = ? FOR UPDATE");
            $stmt->execute([$card_id]);
            $selected_card = $stmt->fetch();

            if ($selected_card) {
                // Fetch up to $quantity available cards of the exact same type and value
                $stmt_cards = $pdo->prepare("SELECT id FROM cards WHERE game_name = ? AND card_value = ? AND status = 'available' LIMIT ? FOR UPDATE");
                $stmt_cards->bindValue(1, $selected_card['game_name'], PDO::PARAM_STR);
                $stmt_cards->bindValue(2, $selected_card['card_value'], PDO::PARAM_STR);
                $stmt_cards->bindValue(3, $quantity, PDO::PARAM_INT);
                $stmt_cards->execute();
                $cards_to_buy = $stmt_cards->fetchAll(PDO::FETCH_COLUMN);

                if (count($cards_to_buy) === $quantity) {
                    // Mark selected cards as sold
                    $placeholders = implode(',', array_fill(0, count($cards_to_buy), '?'));
                    $stmt_update = $pdo->prepare("UPDATE cards SET status = 'sold' WHERE id IN ($placeholders)");
                    $stmt_update->execute($cards_to_buy);

                    // Insert orders
                    $stmt_order = $pdo->prepare("INSERT INTO orders (user_id, card_id, status, payment_method, txid) VALUES (?, ?, ?, ?, ?)");
                    for ($i = 0; $i < count($cards_to_buy); $i++) {
                        $buy_id = $cards_to_buy[$i];
                        $current_txid = null;
                        if (!empty($txid)) {
                            $current_txid = ($i === 0) ? $txid : $txid . "-" . $i;
                        }
                        $stmt_order->execute([$user_id, $buy_id, $status, $payment_method, $current_txid]);
                    }

                    if ($status === 'verified') {
                        notificationCreate(
                            $pdo,
                            $user_id,
                            'order_confirmed',
                            'Order confirmed',
                            'Your order for ' . $selected_card['game_name'] . ' ' . $selected_card['card_value'] . ' has been confirmed.',
                            'dashboard.php'
                        );
                    }

                    $pdo->commit();
                    
                    // Redirect to success page with session stats
                    $_SESSION['success_game_name'] = $selected_card['game_name'];
                    $_SESSION['success_card_value'] = $selected_card['card_value'];
                    $_SESSION['success_quantity'] = $quantity;
                    
                    if ($payment_method === 'payment_app') {
                        $_SESSION['success_txid'] = $txid;
                        $total_price_inr = currencyToDarkPayInr($pdo, floatval($selected_card['price']) * $quantity);
                        header("Location: ../darkpay/pay.php?txid=$txid&amount=$total_price_inr&currency=INR");
                        exit();
                    } else {
                        header("Location: success.php?multiple=true");
                        exit();
                    }
                } else {
                    $pdo->rollBack();
                    $_SESSION['buy_error'] = "Sorry, not enough cards are available in stock (Requested: $quantity, Available: " . count($cards_to_buy) . ").";
                }
            } else {
                $pdo->rollBack();
                $_SESSION['buy_error'] = "Sorry, this card is no longer available.";
            }
        } catch(PDOException $e) {
            $pdo->rollBack();
            $_SESSION['buy_error'] = "An error occurred during payment processing: " . $e->getMessage();
        }
        header("Location: dashboard.php");
        exit();
    }
    
    // Verify Order (Admin Only)
    elseif ($action === 'verify_order' && $_SESSION['role'] === 'admin') {
        $order_id = intval($_POST['order_id']);
        try {
            $stmt_info = $pdo->prepare("SELECT o.user_id, c.game_name, c.card_value FROM orders o JOIN cards c ON c.id = o.card_id WHERE o.id = ?");
            $stmt_info->execute([$order_id]);
            $orderInfo = $stmt_info->fetch();

            $stmt = $pdo->prepare("UPDATE orders SET status = 'verified' WHERE id = ?");
            $stmt->execute([$order_id]);
            if ($orderInfo) {
                notificationCreate($pdo, $orderInfo['user_id'], 'order_confirmed', 'Order confirmed', 'Your order for ' . $orderInfo['game_name'] . ' ' . $orderInfo['card_value'] . ' has been confirmed.', 'dashboard.php');
            }
            $_SESSION['add_success'] = "Order #$order_id has been verified.";
        } catch(PDOException $e) {
            $_SESSION['add_error'] = "Failed to verify order.";
        }
        header("Location: admin.php");
        exit();
    }
    
    // Reject Order (Admin Only)
    elseif ($action === 'reject_order' && $_SESSION['role'] === 'admin') {
        $order_id = intval($_POST['order_id']);
        try {
            $pdo->beginTransaction();
            
            // Fetch card_id of the order
            $stmt_ord = $pdo->prepare("SELECT o.user_id, o.card_id, o.status, c.game_name, c.card_value FROM orders o JOIN cards c ON c.id = o.card_id WHERE o.id = ? FOR UPDATE");
            $stmt_ord->execute([$order_id]);
            $ord = $stmt_ord->fetch();
            
            if ($ord) {
                if ($ord['status'] === 'pending') {
                    // Update order status to rejected
                    $stmt_up_ord = $pdo->prepare("UPDATE orders SET status = 'rejected' WHERE id = ?");
                    $stmt_up_ord->execute([$order_id]);
                    
                    // Reset card status back to available
                    $stmt_up_card = $pdo->prepare("UPDATE cards SET status = 'available' WHERE id = ?");
                    $stmt_up_card->execute([$ord['card_id']]);

                    notificationCreate($pdo, $ord['user_id'], 'order_rejected', 'Order rejected', 'Your order for ' . $ord['game_name'] . ' ' . $ord['card_value'] . ' was rejected. Stock has been restored.', 'dashboard.php');
                    
                    $pdo->commit();
                    $_SESSION['add_success'] = "Order #$order_id successfully rejected and restored to stock inventory!";
                } else {
                    $pdo->rollBack();
                    $_SESSION['add_error'] = "Only pending orders can be rejected.";
                }
            } else {
                $pdo->rollBack();
                $_SESSION['add_error'] = "Order #$order_id not found.";
            }
        } catch(PDOException $e) {
            $pdo->rollBack();
            $_SESSION['add_error'] = "Failed to reject order: " . $e->getMessage();
        }
        header("Location: admin.php");
        exit();
    }
    
    // Deposit Funds (Any logged-in user)
    elseif ($action === 'deposit_funds') {
        darkpayEnsureSchema($pdo);
        $posted_amount = floatval($_POST['amount'] ?? 0);
        $amount_currency = strtoupper($_POST['amount_currency'] ?? 'USD');
        if (!currencyIsSupported($amount_currency)) {
            $amount_currency = 'USD';
        }
        $amount = $posted_amount / currencyRate($pdo, $amount_currency);
        $payment_method = trim($_POST['payment_method'] ?? '');
        $user_id = $_SESSION['user_id'];
        
        if ($amount < 5 || $amount > 5000) {
            $_SESSION['deposit_error'] = "Deposit amount must be between $5 and $5,000.";
            header("Location: dashboard.php");
            exit();
        }
        
        if ($payment_method === 'payment_app') {
            $txid = "DARKPAY-DEP-" . strtoupper(bin2hex(random_bytes(10)));
            try {
                $stmt_dep = $pdo->prepare("INSERT INTO wallet_deposits (user_id, amount, payment_method, txid, status) VALUES (?, ?, ?, ?, 'pending')");
                $stmt_dep->execute([$user_id, $amount, 'payment_app', $txid]);
                
                $darkpay_amount_inr = currencyToDarkPayInr($pdo, $amount);
                header("Location: ../darkpay/pay.php?type=deposit&txid=$txid&amount=$darkpay_amount_inr&currency=INR");
                exit();
            } catch(PDOException $e) {
                $_SESSION['deposit_error'] = "Failed to initialize DarkPay deposit: " . $e->getMessage();
                header("Location: dashboard.php");
                exit();
            }
        } elseif ($payment_method === 'credit_card') {
            $card_name = trim($_POST['card_name'] ?? '');
            $card_number = trim($_POST['card_number'] ?? '');
            
            if (empty($card_name) || empty($card_number)) {
                $_SESSION['deposit_error'] = "Simulated card details are required.";
                header("Location: dashboard.php");
                exit();
            }
            
            try {
                $pdo->beginTransaction();
                
                // Log verified deposit
                $stmt_dep = $pdo->prepare("INSERT INTO wallet_deposits (user_id, amount, payment_method, txid, status) VALUES (?, ?, ?, NULL, 'verified')");
                $stmt_dep->execute([$user_id, $amount, 'credit_card']);
                
                // Credit user wallet
                $stmt_user = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
                $stmt_user->execute([$amount, $user_id]);

                notificationCreate($pdo, $user_id, 'payment_confirmed', 'Deposit confirmed', 'Your wallet deposit of ' . currencyFormat($pdo, $amount, 'USD') . ' was credited.', 'dashboard.php');
                
                $pdo->commit();
                $_SESSION['deposit_success'] = "Successfully deposited $" . number_format($amount, 2) . " via Simulated Card instantly!";
            } catch(PDOException $e) {
                $pdo->rollBack();
                $_SESSION['deposit_error'] = "Simulated Card deposit failed: " . $e->getMessage();
            }
        } elseif (array_key_exists($payment_method, CRYPTO_ADDRESSES)) {
            $txid = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['txid'] ?? '');
            if (empty($txid) || strlen($txid) < 10) {
                $_SESSION['deposit_error'] = "A valid Transaction Hash (TXID) is required for cryptocurrency deposits.";
                header("Location: dashboard.php");
                exit();
            }
            
            // Replay protection: Check if txid already exists in orders or wallet_deposits
            $stmt_check1 = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE txid = ?");
            $stmt_check1->execute([$txid]);
            $stmt_check2 = $pdo->prepare("SELECT COUNT(*) FROM wallet_deposits WHERE txid = ?");
            $stmt_check2->execute([$txid]);
            
            if ($stmt_check1->fetchColumn() > 0 || $stmt_check2->fetchColumn() > 0) {
                $_SESSION['deposit_error'] = "This transaction ID has already been submitted.";
                header("Location: dashboard.php");
                exit();
            }
            
            try {
                // Check blockchain txid status
                $is_verified = verifyCryptoTx($txid, $payment_method, $amount);
                $status = $is_verified ? 'verified' : 'pending';
                
                $pdo->beginTransaction();
                
                // Log deposit ledger record
                $stmt_dep = $pdo->prepare("INSERT INTO wallet_deposits (user_id, amount, payment_method, txid, status) VALUES (?, ?, ?, ?, ?)");
                $stmt_dep->execute([$user_id, $amount, $payment_method, $txid, $status]);
                
                if ($is_verified) {
                    // Credit user balance instantly
                    $stmt_user = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
                    $stmt_user->execute([$amount, $user_id]);
                    notificationCreate($pdo, $user_id, 'payment_confirmed', 'Deposit confirmed', 'Your crypto deposit of ' . currencyFormat($pdo, $amount, 'USD') . ' was verified and credited.', 'dashboard.php');
                    $_SESSION['deposit_success'] = "Crypto transaction automatically verified! $" . number_format($amount, 2) . " added to wallet.";
                } else {
                    $_SESSION['deposit_success'] = "Crypto deposit submitted! Your transaction is pending verification review.";
                }
                
                $pdo->commit();
            } catch(PDOException $e) {
                $pdo->rollBack();
                $_SESSION['deposit_error'] = "Crypto deposit failed: " . $e->getMessage();
            }
        } else {
            $_SESSION['deposit_error'] = "Invalid payment method chosen.";
        }
        header("Location: dashboard.php");
        exit();
    }
    
    // Approve Wallet Deposit (Admin Only)
    elseif ($action === 'approve_deposit' && $_SESSION['role'] === 'admin') {
        $deposit_id = intval($_POST['deposit_id']);
        try {
            $pdo->beginTransaction();
            
            // Get deposit detail
            $stmt_dep = $pdo->prepare("SELECT user_id, amount, status FROM wallet_deposits WHERE id = ? FOR UPDATE");
            $stmt_dep->execute([$deposit_id]);
            $dep = $stmt_dep->fetch();
            
            if ($dep) {
                if ($dep['status'] === 'pending') {
                    // Mark as verified
                    $stmt_up = $pdo->prepare("UPDATE wallet_deposits SET status = 'verified' WHERE id = ?");
                    $stmt_up->execute([$deposit_id]);
                    
                    // Credit user's wallet balance
                    $stmt_usr = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
                    $stmt_usr->execute([$dep['amount'], $dep['user_id']]);

                    notificationCreate($pdo, $dep['user_id'], 'payment_confirmed', 'Deposit approved', 'Your wallet deposit of ' . currencyFormat($pdo, $dep['amount'], 'USD') . ' has been approved and credited.', 'dashboard.php');
                    
                    $pdo->commit();
                    $_SESSION['add_success'] = "Deposit #$deposit_id has been approved and user wallet credited.";
                } else {
                    $pdo->rollBack();
                    $_SESSION['add_error'] = "Only pending deposits can be approved.";
                }
            } else {
                $pdo->rollBack();
                $_SESSION['add_error'] = "Deposit #$deposit_id not found.";
            }
        } catch(PDOException $e) {
            $pdo->rollBack();
            $_SESSION['add_error'] = "Failed to approve deposit: " . $e->getMessage();
        }
        header("Location: admin.php");
        exit();
    }
    
    // Reject Wallet Deposit (Admin Only)
    elseif ($action === 'reject_deposit' && $_SESSION['role'] === 'admin') {
        $deposit_id = intval($_POST['deposit_id']);
        try {
            $pdo->beginTransaction();
            
            // Get deposit detail
            $stmt_dep = $pdo->prepare("SELECT user_id, amount, status FROM wallet_deposits WHERE id = ? FOR UPDATE");
            $stmt_dep->execute([$deposit_id]);
            $dep = $stmt_dep->fetch();
            
            if ($dep) {
                if ($dep['status'] === 'pending') {
                    // Mark as rejected
                    $stmt_up = $pdo->prepare("UPDATE wallet_deposits SET status = 'rejected' WHERE id = ?");
                    $stmt_up->execute([$deposit_id]);

                    notificationCreate($pdo, $dep['user_id'], 'payment_rejected', 'Deposit rejected', 'Your wallet deposit of ' . currencyFormat($pdo, $dep['amount'], 'USD') . ' was rejected.', 'dashboard.php');
                    
                    $pdo->commit();
                    $_SESSION['add_success'] = "Deposit #$deposit_id has been rejected.";
                } else {
                    $pdo->rollBack();
                    $_SESSION['add_error'] = "Only pending deposits can be rejected.";
                }
            } else {
                $pdo->rollBack();
                $_SESSION['add_error'] = "Deposit #$deposit_id not found.";
            }
        } catch(PDOException $e) {
            $pdo->rollBack();
            $_SESSION['add_error'] = "Failed to reject deposit: " . $e->getMessage();
        }
        header("Location: admin.php");
        exit();
    }
    
    // Add Review (Any logged-in user with a verified order)
    elseif ($action === 'add_review') {
        $order_id = intval($_POST['order_id']);
        $game_name = trim($_POST['game_name']);
        $rating = intval($_POST['rating']);
        $comment = trim($_POST['comment']);
        $user_id = $_SESSION['user_id'];

        if (empty($game_name) || empty($comment) || $rating < 1 || $rating > 5) {
            $_SESSION['review_error'] = "Invalid review data. Please fill all fields and rate 1-5 stars.";
            header("Location: dashboard.php");
            exit();
        }

        try {
            // Verify ownership and status of order
            $stmt = $pdo->prepare("SELECT status FROM orders WHERE id = ? AND user_id = ?");
            $stmt->execute([$order_id, $user_id]);
            $order = $stmt->fetch();

            if (!$order) {
                $_SESSION['review_error'] = "Invalid order ID or ownership.";
            } elseif ($order['status'] !== 'verified') {
                $_SESSION['review_error'] = "You can only review verified orders.";
            } else {
                // Verify if already reviewed
                $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM reviews WHERE order_id = ?");
                $stmt_check->execute([$order_id]);
                if ($stmt_check->fetchColumn() > 0) {
                    $_SESSION['review_error'] = "You have already reviewed this purchase.";
                } else {
                    // Insert review
                    $stmt_ins = $pdo->prepare("INSERT INTO reviews (user_id, order_id, game_name, rating, comment) VALUES (?, ?, ?, ?, ?)");
                    $stmt_ins->execute([$user_id, $order_id, $game_name, $rating, $comment]);
                    $_SESSION['review_success'] = "Thank you! Your star review has been shared.";
                }
            }
        } catch (PDOException $e) {
            $_SESSION['review_error'] = "Failed to submit review: " . $e->getMessage();
        }

        header("Location: dashboard.php");
        exit();
    }
}

// Redirect back if nothing matched
header("Location: dashboard.php");
exit();
?>
