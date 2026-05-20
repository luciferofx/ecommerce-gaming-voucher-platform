<?php
require_once 'includes/db.php';
require_once 'includes/notifications.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$action = $_GET['action'] ?? '';
if ($action === 'read_all') {
    notificationMarkAllRead($pdo, $_SESSION['user_id']);
}

$redirect = $_GET['redirect'] ?? $_SERVER['HTTP_REFERER'] ?? 'dashboard.php';
if (preg_match('/[\r\n]/', $redirect) || preg_match('#^https?://#i', $redirect)) {
    $redirect = 'dashboard.php';
}

header('Location: ' . $redirect);
exit();
?>
