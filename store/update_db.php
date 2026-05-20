<?php
// update_db.php - Robust database migration script
require_once 'includes/db.php';

try {
    // 1. Manually check if the 'status' column exists in 'orders' table
    $stmt = $pdo->query("SHOW COLUMNS FROM `orders` LIKE 'status'");
    $columnExists = $stmt->fetch();

    if (!$columnExists) {
        // If it doesn't exist, safely add it
        $pdo->exec("ALTER TABLE `orders` ADD COLUMN `status` ENUM('pending', 'verified', 'rejected') DEFAULT 'pending' AFTER `card_id`");
        echo "Column 'status' successfully added to the 'orders' table!<br>";
    } else {
        // Upgrade the ENUM to include 'rejected'
        $pdo->exec("ALTER TABLE `orders` MODIFY COLUMN `status` ENUM('pending', 'verified', 'rejected') DEFAULT 'pending'");
        echo "Column 'status' ENUM successfully upgraded to include 'rejected'!<br>";
    }

    // Set existing orders to 'verified' so old purchases don't get locked out
    $pdo->exec("UPDATE `orders` SET `status` = 'verified' WHERE `status` IS NULL");
    echo "Existing null orders set to verified.<br>";

    // 2. Add payment_method column to orders if it does not exist
    $stmt_pm = $pdo->query("SHOW COLUMNS FROM `orders` LIKE 'payment_method'");
    if (!$stmt_pm->fetch()) {
        $pdo->exec("ALTER TABLE `orders` ADD COLUMN `payment_method` VARCHAR(50) DEFAULT 'credit_card' AFTER `status`");
        echo "Column 'payment_method' successfully added to the 'orders' table!<br>";
    } else {
        echo "Column 'payment_method' already exists in the 'orders' table.<br>";
    }

    // 3. Add txid column to orders if it does not exist
    $stmt_tx = $pdo->query("SHOW COLUMNS FROM `orders` LIKE 'txid'");
    if (!$stmt_tx->fetch()) {
        $pdo->exec("ALTER TABLE `orders` ADD COLUMN `txid` VARCHAR(100) DEFAULT NULL AFTER `payment_method`");
        $pdo->exec("ALTER TABLE `orders` ADD UNIQUE INDEX unique_txid (`txid`)");
        echo "Column 'txid' successfully added as UNIQUE to the 'orders' table!<br>";
    } else {
        echo "Column 'txid' already exists in the 'orders' table.<br>";
    }

    // 4. Add wallet_balance column to users if it does not exist
    $stmt_wb = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'wallet_balance'");
    if (!$stmt_wb->fetch()) {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `wallet_balance` DECIMAL(10, 2) DEFAULT 0.00 AFTER `role`");
        echo "Column 'wallet_balance' successfully added to the 'users' table!<br>";
    } else {
        echo "Column 'wallet_balance' already exists in the 'users' table.<br>";
    }

    // 5. Add login_token column to users if it does not exist
    $stmt_lt = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'login_token'");
    if (!$stmt_lt->fetch()) {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `login_token` VARCHAR(100) DEFAULT NULL AFTER `wallet_balance`");
        $pdo->exec("ALTER TABLE `users` ADD UNIQUE INDEX unique_token (`login_token`)");
        echo "Column 'login_token' successfully added as UNIQUE to the 'users' table!<br>";
    } else {
        echo "Column 'login_token' already exists in the 'users' table.<br>";
    }

    // 6. Generate secure Login Tokens for any existing users lacking one
    $stmt_users = $pdo->query("SELECT id FROM `users` WHERE `login_token` IS NULL OR `login_token` = ''");
    $users_without_token = $stmt_users->fetchAll();
    if (count($users_without_token) > 0) {
        $stmt_update_token = $pdo->prepare("UPDATE `users` SET `login_token` = ? WHERE `id` = ?");
        foreach ($users_without_token as $usr) {
            $token = "DARK-TOKEN-" . strtoupper(bin2hex(random_bytes(12)));
            $stmt_update_token->execute([$token, $usr['id']]);
        }
        echo "Successfully generated secure Access Tokens for " . count($users_without_token) . " users!<br>";
    }

    // 7. Create Reviews Table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS reviews (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        order_id INT NOT NULL UNIQUE,
        game_name VARCHAR(100) NOT NULL,
        rating INT NOT NULL,
        comment TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (order_id) REFERENCES orders(id)
    )");
    echo "Reviews table successfully verified or created!<br>";

    // 8. Create Wallet Deposits Table if not exists
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
    echo "Wallet Deposits table successfully verified or created!<br>";

    // 8b. Create Notifications Table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        type VARCHAR(40) NOT NULL,
        title VARCHAR(160) NOT NULL,
        body TEXT NOT NULL,
        link VARCHAR(255) DEFAULT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_read_created (user_id, is_read, created_at),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "Notifications table successfully verified or created!<br>";

    // 9. Add production DarkPay verification fields.
    $darkpayOrderColumns = [
        'customer_upi_ref' => "ALTER TABLE `orders` ADD COLUMN `customer_upi_ref` VARCHAR(100) DEFAULT NULL AFTER `txid`",
        'provider_txid' => "ALTER TABLE `orders` ADD COLUMN `provider_txid` VARCHAR(100) DEFAULT NULL AFTER `customer_upi_ref`",
        'paid_amount' => "ALTER TABLE `orders` ADD COLUMN `paid_amount` DECIMAL(10, 2) DEFAULT NULL AFTER `provider_txid`",
        'verified_at' => "ALTER TABLE `orders` ADD COLUMN `verified_at` TIMESTAMP NULL DEFAULT NULL AFTER `paid_amount`",
        'provider_payload' => "ALTER TABLE `orders` ADD COLUMN `provider_payload` TEXT DEFAULT NULL AFTER `verified_at`",
    ];

    foreach ($darkpayOrderColumns as $column => $sql) {
        $stmt_col = $pdo->query("SHOW COLUMNS FROM `orders` LIKE '$column'");
        if (!$stmt_col->fetch()) {
            $pdo->exec($sql);
            echo "DarkPay order column '$column' added.<br>";
        }
    }

    $darkpayDepositColumns = [
        'customer_upi_ref' => "ALTER TABLE `wallet_deposits` ADD COLUMN `customer_upi_ref` VARCHAR(100) DEFAULT NULL AFTER `txid`",
        'provider_txid' => "ALTER TABLE `wallet_deposits` ADD COLUMN `provider_txid` VARCHAR(100) DEFAULT NULL AFTER `customer_upi_ref`",
        'paid_amount' => "ALTER TABLE `wallet_deposits` ADD COLUMN `paid_amount` DECIMAL(10, 2) DEFAULT NULL AFTER `provider_txid`",
        'verified_at' => "ALTER TABLE `wallet_deposits` ADD COLUMN `verified_at` TIMESTAMP NULL DEFAULT NULL AFTER `paid_amount`",
        'provider_payload' => "ALTER TABLE `wallet_deposits` ADD COLUMN `provider_payload` TEXT DEFAULT NULL AFTER `verified_at`",
    ];

    foreach ($darkpayDepositColumns as $column => $sql) {
        $stmt_col = $pdo->query("SHOW COLUMNS FROM `wallet_deposits` LIKE '$column'");
        if (!$stmt_col->fetch()) {
            $pdo->exec($sql);
            echo "DarkPay deposit column '$column' added.<br>";
        }
    }

    // 10. Create app settings for currency conversion.
    $pdo->exec("CREATE TABLE IF NOT EXISTS app_settings (
        setting_key VARCHAR(80) NOT NULL PRIMARY KEY,
        setting_value VARCHAR(255) NOT NULL
    )");
    $stmt_setting = $pdo->prepare("INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES (?, ?)");
    $stmt_setting->execute(['store_base_currency', 'USD']);
    $stmt_setting->execute(['fx_rates_json', '{"USD":1,"EUR":0.92,"INR":85.00}']);
    $stmt_setting->execute(['fx_rates_updated_at', '1970-01-01 00:00:00']);
    echo "Currency app settings verified.<br>";
    
    echo "<h3>Database updates successful!</h3>";
    echo "<a href='index.php'>Go to homepage</a>";
} catch(PDOException $e) {
    die("Database migration failed: " . $e->getMessage());
}
?>
