# DarkPay UPI Production Setup

DarkPay now has a production-safe approval flow:

1. The store creates an internal `DARKPAY-*` reference.
2. The customer pays by UPI and may submit their UTR/reference for matching.
3. The order stays `pending`.
4. `darkpay/webhook.php` verifies a signed provider webhook.
5. Only then DarkPay marks the order/deposit `verified`.

Required environment variables:

```ini
DARKPAY_UPI_VPA=yourmerchant@bank
DARKPAY_MERCHANT_NAME=Dark Gaming Store
DARKPAY_WEBHOOK_SECRET=replace-with-provider-webhook-secret
```

Users can choose USD, EUR, or INR from the store navbar. Product prices are stored as USD base, live exchange rates are cached by the app, and the live USD to INR rate is used for DarkPay UPI redirects and webhook amount verification.

Webhook URL to configure in the payment provider dashboard:

```text
https://your-domain.example/darkpay/webhook.php
```

The webhook payload must include:

```json
{
  "payment": {
    "status": "success",
    "reference": "DARKPAY-...",
    "type": "checkout",
    "upi_transaction_id": "provider-or-bank-utr",
    "amount_decimal": 8500.00
  }
}
```

For Razorpay-style webhooks, `payment.captured` and `payload.payment.entity` are also accepted. If your provider sends paise/minor units, map that value to `amount_minor`, or send rupees as `amount_decimal`. The signature header must be `X-DarkPay-Signature` or `X-Razorpay-Signature`, calculated as HMAC-SHA256 over the raw request body with `DARKPAY_WEBHOOK_SECRET`.

Do not approve orders only from a customer-entered UTR. UTR text is useful for support matching, but the provider webhook is the trusted signal.
