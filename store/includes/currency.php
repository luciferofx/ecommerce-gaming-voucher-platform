<?php
// Currency helpers. Product prices stay stored in USD base.

const CURRENCY_CODES = ['USD', 'EUR', 'INR'];
const CURRENCY_SYMBOLS = [
    'USD' => '$',
    'EUR' => '€',
    'INR' => '₹',
];

function currencyEnsureSettings(PDO $pdo)
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS app_settings (
        setting_key VARCHAR(80) NOT NULL PRIMARY KEY,
        setting_value TEXT NOT NULL
    )");

    $defaults = [
        'store_base_currency' => 'USD',
        'fx_rates_json' => json_encode(['USD' => 1, 'EUR' => 0.92, 'INR' => 85.00]),
        'fx_rates_updated_at' => '1970-01-01 00:00:00',
    ];

    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM app_settings WHERE setting_key = ?");
    $stmtInsert = $pdo->prepare("INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?)");
    foreach ($defaults as $key => $value) {
        $stmtCheck->execute([$key]);
        if ((int) $stmtCheck->fetchColumn() === 0) {
            $stmtInsert->execute([$key, $value]);
        }
    }
}

function currencySetting(PDO $pdo, $key, $default = '')
{
    currencyEnsureSettings($pdo);
    $stmt = $pdo->prepare("SELECT setting_value FROM app_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    return $value === false ? $default : $value;
}

function currencySaveSetting(PDO $pdo, $key, $value)
{
    currencyEnsureSettings($pdo);
    $stmt = $pdo->prepare("INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->execute([$key, $value]);
}

function currencyIsSupported($currency)
{
    return in_array(strtoupper((string) $currency), CURRENCY_CODES, true);
}

function currencySetUserCurrency($currency)
{
    $currency = strtoupper((string) $currency);
    if (!currencyIsSupported($currency)) {
        $currency = 'USD';
    }

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $_SESSION['display_currency'] = $currency;
    setcookie('display_currency', $currency, time() + 31536000, '/');
}

function currencyDisplayCode(PDO $pdo = null)
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $currency = strtoupper($_SESSION['display_currency'] ?? $_COOKIE['display_currency'] ?? 'USD');
    return currencyIsSupported($currency) ? $currency : 'USD';
}

function currencyFetchLiveRates()
{
    $urls = [
        'https://open.er-api.com/v6/latest/USD',
        'https://api.frankfurter.app/latest?from=USD&to=EUR,INR',
    ];

    foreach ($urls as $url) {
        $json = @file_get_contents($url);
        if (!$json) {
            continue;
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            continue;
        }

        $rates = $data['rates'] ?? [];
        if (isset($rates['EUR'], $rates['INR'])) {
            return [
                'USD' => 1.0,
                'EUR' => (float) $rates['EUR'],
                'INR' => (float) $rates['INR'],
            ];
        }
    }

    return null;
}

function currencyRates(PDO $pdo, $forceRefresh = false)
{
    currencyEnsureSettings($pdo);
    $updatedAt = strtotime(currencySetting($pdo, 'fx_rates_updated_at', '1970-01-01 00:00:00'));
    $isStale = $updatedAt === false || (time() - $updatedAt) > 3600;

    if ($forceRefresh || $isStale) {
        $liveRates = currencyFetchLiveRates();
        if ($liveRates) {
            currencySaveSetting($pdo, 'fx_rates_json', json_encode($liveRates));
            currencySaveSetting($pdo, 'fx_rates_updated_at', date('Y-m-d H:i:s'));
            return $liveRates;
        }
    }

    $stored = json_decode(currencySetting($pdo, 'fx_rates_json', '{}'), true);
    if (!is_array($stored)) {
        $stored = [];
    }

    return [
        'USD' => 1.0,
        'EUR' => isset($stored['EUR']) ? (float) $stored['EUR'] : 0.92,
        'INR' => isset($stored['INR']) ? (float) $stored['INR'] : 85.00,
    ];
}

function currencyRate(PDO $pdo, $targetCurrency)
{
    $targetCurrency = strtoupper($targetCurrency);
    $rates = currencyRates($pdo);
    return $rates[$targetCurrency] ?? 1.0;
}

function currencyConvertFromUsd(PDO $pdo, $amountUsd, $targetCurrency = null)
{
    $targetCurrency = $targetCurrency ? strtoupper($targetCurrency) : currencyDisplayCode($pdo);
    $amountUsd = (float) $amountUsd;
    return $amountUsd * currencyRate($pdo, $targetCurrency);
}

function currencyFormat(PDO $pdo, $amountUsd, $targetCurrency = null)
{
    $targetCurrency = $targetCurrency ? strtoupper($targetCurrency) : currencyDisplayCode($pdo);
    $amount = currencyConvertFromUsd($pdo, $amountUsd, $targetCurrency);
    $symbol = CURRENCY_SYMBOLS[$targetCurrency] ?? '$';
    return $symbol . number_format($amount, 2) . ' ' . $targetCurrency;
}

function currencyToDarkPayInr(PDO $pdo, $amountUsd)
{
    return round(currencyConvertFromUsd($pdo, $amountUsd, 'INR'), 2);
}

function currencyLastUpdated(PDO $pdo)
{
    return currencySetting($pdo, 'fx_rates_updated_at', '1970-01-01 00:00:00');
}
?>
