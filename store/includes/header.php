<?php
// includes/header.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (isset($pdo) && !function_exists('currencyDisplayCode')) {
    require_once __DIR__ . '/currency.php';
}
if (isset($pdo) && !function_exists('notificationUnreadCount')) {
    require_once __DIR__ . '/notifications.php';
}
$active_currency = isset($pdo) ? currencyDisplayCode($pdo) : ($_SESSION['display_currency'] ?? 'USD');
$current_path = basename($_SERVER['PHP_SELF'] ?? 'shop.php');
$currency_redirect = $current_path . (empty($_SERVER['QUERY_STRING']) ? '' : '?' . $_SERVER['QUERY_STRING']);
$store_base_url = strpos($_SERVER['SCRIPT_NAME'] ?? '', '/darkpay/') !== false ? '../store/' : '';
$darkpay_base_url = strpos($_SERVER['SCRIPT_NAME'] ?? '', '/darkpay/') !== false ? '' : '../darkpay/';
$notification_redirect = strpos($_SERVER['SCRIPT_NAME'] ?? '', '/darkpay/') !== false ? '../darkpay/' . $currency_redirect : $currency_redirect;
$notifications = [];
$unread_notifications = 0;
if (isset($pdo, $_SESSION['user_id'])) {
    $unread_notifications = notificationUnreadCount($pdo, $_SESSION['user_id']);
    $notifications = notificationRecent($pdo, $_SESSION['user_id']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dark Gaming Cards</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $store_base_url; ?>assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo $store_base_url; ?>assets/css/modal.css?v=<?php echo time(); ?>">
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <a href="<?php echo $store_base_url; ?>index.php" class="logo">Dark Gaming</a>
            <ul class="nav-links">
                <li><a href="<?php echo $store_base_url; ?>index.php">Home</a></li>
                <li><a href="<?php echo $store_base_url; ?>shop.php">Shop</a></li>
                <li><a href="<?php echo $store_base_url; ?>reviews.php">Reviews</a></li>
                <li>
                    <form action="<?php echo $store_base_url; ?>set_currency.php" method="POST" style="margin: 0;">
                        <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($currency_redirect); ?>">
                        <select name="currency" onchange="this.form.submit()" style="background: #1a1a1a; color: #fff; border: 1px solid #444; border-radius: 4px; padding: 5px 8px; font-weight: 600;">
                            <option value="USD" <?php echo $active_currency === 'USD' ? 'selected' : ''; ?>>USD</option>
                            <option value="EUR" <?php echo $active_currency === 'EUR' ? 'selected' : ''; ?>>EUR</option>
                            <option value="INR" <?php echo $active_currency === 'INR' ? 'selected' : ''; ?>>INR</option>
                        </select>
                    </form>
                </li>
                <?php if(isset($_SESSION['user_id'])): ?>
                    <li><span style="color: #aaa; font-weight: 500;">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span></li>
                    <li class="notification-nav">
                        <button type="button" class="notification-toggle" onclick="document.getElementById('notificationDropdown').classList.toggle('open')">
                            Notifications
                            <?php if ($unread_notifications > 0): ?>
                                <span class="notification-badge"><?php echo $unread_notifications; ?></span>
                            <?php endif; ?>
                        </button>
                        <div id="notificationDropdown" class="notification-dropdown">
                            <div class="notification-head">
                                <strong>Updates</strong>
                                <?php if ($unread_notifications > 0): ?>
                                    <a href="<?php echo $store_base_url; ?>notifications.php?action=read_all&redirect=<?php echo urlencode($notification_redirect); ?>">Mark read</a>
                                <?php endif; ?>
                            </div>
                            <?php if (empty($notifications)): ?>
                                <div class="notification-empty">No notifications yet.</div>
                            <?php else: ?>
                                <?php foreach ($notifications as $note): ?>
                                    <a class="notification-item <?php echo $note['is_read'] ? '' : 'unread'; ?>" href="<?php echo $store_base_url . htmlspecialchars($note['link'] ?: 'dashboard.php'); ?>">
                                        <span class="notification-title"><?php echo htmlspecialchars($note['title']); ?></span>
                                        <span class="notification-body"><?php echo htmlspecialchars($note['body']); ?></span>
                                        <span class="notification-time"><?php echo date('M d, H:i', strtotime($note['created_at'])); ?></span>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </li>
                    <?php if($_SESSION['role'] === 'admin'): ?>
                        <li><a href="<?php echo $store_base_url; ?>admin.php">Admin Panel</a></li>
                        <li><a href="<?php echo $darkpay_base_url; ?>admin.php">DarkPay</a></li>
                    <?php endif; ?>
                    <li><a href="<?php echo $store_base_url; ?>dashboard.php">Dashboard</a></li>
                    <li><a href="<?php echo $store_base_url; ?>process_auth.php?action=logout" class="btn-logout" style="color: var(--danger); border: 1px solid var(--danger); padding: 4px 10px; border-radius: 4px; transition: all 0.3s;">Logout</a></li>
                <?php else: ?>
                    <li><a href="<?php echo $store_base_url; ?>login.php" class="btn btn-secondary" style="padding: 6px 15px; font-size: 0.9rem; color: #000;">Login / Register</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>
    <div class="main-container container">
