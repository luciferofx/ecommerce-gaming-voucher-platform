<?php
require_once 'includes/db.php';
require_once 'includes/currency.php';

$currency = $_POST['currency'] ?? $_GET['currency'] ?? 'USD';
currencySetUserCurrency($currency);

$redirect = $_POST['redirect'] ?? $_SERVER['HTTP_REFERER'] ?? 'shop.php';
if (preg_match('/[\r\n]/', $redirect) || preg_match('#^https?://#i', $redirect)) {
    $redirect = 'shop.php';
}

header('Location: ' . $redirect);
exit();
?>
