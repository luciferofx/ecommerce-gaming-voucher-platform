# ecommerce-gaming-voucher-platform 🎮

A full-stack e-commerce platform for selling gaming vouchers, prepaid cards, and digital payment keys with **DarkPay** - a secure UPI payment gateway integration.

---

## 📋 Project Overview

**ecommerce-gaming-voucher-platform** is a complete gaming card marketplace built with:
- **Backend**: PHP with PDO database abstraction
- **Frontend**: Responsive HTML/CSS/JavaScript
- **Payment Gateway**: DarkPay (Custom UPI integration)
- **Database**: MySQL
- **Authentication**: User login/registration system
- **Admin Panel**: Card management & payment verification

### Key Features ✨

✅ **User Management**
- Secure registration & login with password hashing
- User dashboard & order history
- Customer reviews & ratings system

✅ **Product Management**
- Dynamic gaming card catalog
- Multi-currency support (USD, EUR, INR)
- Live exchange rate caching
- Admin inventory management

✅ **DarkPay Payment Gateway**
- UPI payment integration
- Webhook-based payment verification
- Support for multiple payment providers (Razorpay, custom UPI)
- HMAC-SHA256 signature verification
- Pending/Verified order workflow

✅ **Admin Dashboard**
- Payment verification panel
- Order management
- Card inventory control
- Gateway configuration
- Payment statistics

✅ **Security Features**
- HMAC webhook signature verification
- Encrypted sensitive data
- Admin-only verification flow
- UUID-based transaction IDs

---

## 🛠️ Tech Stack

| Component | Technology |
|-----------|-----------|
| **Backend** | PHP 7.4+ |
| **Database** | MySQL 5.7+ |
| **Frontend** | HTML5, CSS3, JavaScript (Vanilla) |
| **Payment API** | DarkPay (UPI) |
| **Currency Conversion** | Live exchange rate API |
| **Authentication** | PHP Sessions + Password Hash |

---

## 📁 Project Structure

```
dark/
├── darkpay/                    # Payment gateway module
│   ├── admin.php              # Admin payment dashboard
│   ├── admin_action.php       # Admin actions handler
│   ├── webhook.php            # Payment webhook receiver
│   ├── process_payment.php    # Payment processing
│   ├── status.php             # Payment status API
│   ├── pay.php                # Payment initiation
│   ├── includes/
│   │   └── darkpay_gateway.php # DarkPay library
│   ├── assets/
│   │   ├── css/pay_style.css
│   │   └── js/pay.js
│   ├── PRODUCTION_UPI_SETUP.md # Deployment guide
│
└── store/                      # Main e-commerce store
    ├── index.php              # Homepage with featured products
    ├── shop.php               # Product listing & filtering
    ├── checkout.php           # Checkout process
    ├── dashboard.php          # User dashboard
    ├── login.php              # Authentication
    ├── admin.php              # Admin panel
    ├── reviews.php            # Customer reviews
    ├── notifications.php      # Notifications system
    ├── success.php            # Order confirmation
    ├── ecommerce-gaming-voucher-platform.sql        # Database schema
    ├── includes/
    │   ├── db.php             # Database connection
    │   ├── header.php         # Navigation & layout
    │   ├── footer.php         # Footer template
    │   ├── currency.php       # Multi-currency logic
    │   ├── crypto_helper.php  # Encryption utilities
    │   └── notifications.php  # Notification handlers
    └── assets/
        └── css/
            ├── style.css
            └── modal.css
```

---

## 🚀 Installation Guide

### Prerequisites
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- Composer (optional, for dependencies)

### Step 1: Clone the Repository

```bash
git clone https://github.com/luciferofx/ecommerce-gaming-voucher-platform.git
cd dark-gaming-store
```

### Step 2: Setup Database

1. Create a MySQL database:
```bash
mysql -u root -p
```

```sql
CREATE DATABASE ecommerce-gaming-voucher-platform;
USE ecommerce-gaming-voucher-platform;
```

2. Import the schema:
```bash
mysql -u root -p ecommerce-gaming-voucher-platform < store/ecommerce-gaming-voucher-platform.sql
```

### Step 3: Configure Database Connection

Edit `store/includes/db.php`:

```php
$host = 'localhost';
$db = 'ecommerce-gaming-voucher-platform';
$user = 'root';              // Your MySQL username
$password = '';              // Your MySQL password
```

### Step 4: Set Environment Variables

Create a `.env` file in the root directory:

```ini
# DarkPay Configuration
DARKPAY_UPI_VPA=yourmerchant@bank
DARKPAY_MERCHANT_NAME=ecommerce-gaming-voucher-platform
DARKPAY_WEBHOOK_SECRET=your-webhook-secret-key

# Optional: Payment Provider
RAZORPAY_KEY_ID=your-razorpay-key
RAZORPAY_KEY_SECRET=your-razorpay-secret
```

### Step 5: Start Development Server

```bash
# Using PHP built-in server
php -S localhost:8000

# Or configure your web server root to point to the project directory
```

### Step 6: Access the Application

- **Store**: `http://localhost:8000/store/`
- **Admin**: `http://localhost:8000/darkpay/admin.php`
- **Default Admin Credentials**: 
  - Username: `admin`
  - Password: `admin123`

---

## 🔧 Configuration

### DarkPay Webhook Setup

1. In your payment provider dashboard, set webhook URL to:
```
https://yourdomain.com/darkpay/webhook.php
```

2. Configure webhook payload structure (supports multiple formats):

**Standard Format:**
```json
{
  "payment": {
    "status": "success",
    "reference": "DARKPAY-ORDER-ID",
    "type": "checkout",
    "upi_transaction_id": "PROVIDER-UTR",
    "amount_decimal": 5000.00
  }
}
```

**Razorpay Format:**
```json
{
  "event": "payment.captured",
  "payload": {
    "payment": {
      "entity": {
        "id": "pay_123456",
        "captured": true,
        "amount": 500000,
        "notes": {
          "darkpay_reference": "DARKPAY-ORDER-ID"
        }
      }
    }
  }
}
```

3. Set webhook secret in configuration and .env file

### Multi-Currency Configuration

The store supports USD, EUR, and INR currencies. Exchange rates are:
- Fetched from a live API
- Cached for performance
- Applied to product prices automatically

---

## 📊 Database Schema

### Tables

**users**
- `id` (INT, Primary Key)
- `username` (VARCHAR, Unique)
- `password` (VARCHAR, Hashed)
- `role` (ENUM: 'user', 'admin')
- `created_at` (TIMESTAMP)

**cards**
- `id` (INT, Primary Key)
- `game_name` (VARCHAR)
- `card_value` (VARCHAR)
- `code` (VARCHAR, Unique)
- `price` (DECIMAL, USD)
- `status` (ENUM: 'available', 'sold')
- `created_at` (TIMESTAMP)

**orders**
- `id` (INT, Primary Key)
- `user_id` (INT, FK → users)
- `card_id` (INT, FK → cards)
- `status` (ENUM: 'pending', 'verified')
- `purchase_date` (TIMESTAMP)

**reviews** (if exists)
- `id` (INT, Primary Key)
- `user_id` (INT, FK)
- `rating` (INT)
- `comment` (TEXT)
- `created_at` (TIMESTAMP)

---

## 🔐 Security Features

✅ **Password Hashing**: Uses PHP's `password_hash()` (bcrypt)
✅ **HMAC Verification**: Webhook signatures validated with HMAC-SHA256
✅ **SQL Injection Protection**: PDO prepared statements
✅ **Admin-Only Verification**: Orders verified only after webhook confirmation
✅ **Session Management**: Secure PHP sessions with role-based access

---

## 📱 API Endpoints

### Payment Status
- **GET** `/darkpay/status.php?txid={transaction_id}`
  - Returns payment verification status

### Webhook
- **POST** `/darkpay/webhook.php`
  - Receives and processes payment provider webhooks
  - Requires valid HMAC signature

### Store APIs
- **GET** `/store/shop.php` - Product listing
- **GET** `/store/checkout.php?card_id={id}` - Checkout page
- **POST** `/store/process_auth.php` - Authentication
- **POST** `/store/process_action.php` - Order actions

---

## 🌐 Free Hosting & Database Solutions

### Option 1: Railway.app ⭐ (Recommended)
- **Free tier**: 500 hours/month (more than enough)
- **MySQL Support**: Yes (built-in)
- **GitHub Integration**: One-click deploy
- **Website**: https://railway.app

**Setup Steps:**
1. Sign up with GitHub
2. Create new project → Select MySQL
3. Add PHP service (choose railway starter template)
4. Connect GitHub repo
5. Auto-deploys on push

### Option 2: Heroku + Planetscale
- **Heroku**: Web hosting (free tier removed, but use alternatives)
- **PlanetScale**: Free MySQL (5GB, perfect for this)
- **Website**: 
  - https://planetscale.com (MySQL)

**PlanetScale Setup:**
```bash
npm install -g pscale
pscale auth login
pscale database create ecommerce-gaming-voucher-platform
pscale connect ecommerce-gaming-voucher-platform
# Use provided connection string in db.php
```

### Option 3: Render.com + Supabase
- **Render**: Deploy PHP (free tier available)
- **Supabase**: PostgreSQL/MySQL (free 500MB)
- **Website**: 
  - https://render.com
  - https://supabase.com

### Option 4: InfinityFree (Simple Option)
- **Free Hosting**: 1 PHP account
- **MySQL**: Included
- **Domain**: Free .rf.gd subdomain
- **Website**: https://www.infinityfree.net

---

## 📤 GitHub Deployment Setup

### Step 1: Push to GitHub

```bash
git init
git add .
git commit -m "Initial commit: ecommerce-gaming-voucher-platform with DarkPay"
git branch -M main
git remote add origin https://github.com/yourusername/dark-gaming-store.git
git push -u origin main
```

### Step 2: Create `.env.example`

```ini
# Database
DB_HOST=localhost
DB_USER=root
DB_PASSWORD=
DB_NAME=ecommerce-gaming-voucher-platform

# DarkPay Configuration
DARKPAY_UPI_VPA=merchant@upi
DARKPAY_MERCHANT_NAME=ecommerce-gaming-voucher-platform
DARKPAY_WEBHOOK_SECRET=change-this-in-production

# Payment Providers (Optional)
RAZORPAY_KEY_ID=
RAZORPAY_KEY_SECRET=
```

### Step 3: Add `.gitignore`

```
.env
.env.local
*.log
.DS_Store
node_modules/
/vendor/
.vscode/
*.swp
config.local.php
```

### Step 4: Deploy to Railway

1. Go to **railway.app** → Sign in with GitHub
2. New Project → GitHub Repo
3. Select this repository
4. Add variables from `.env.example`
5. Deploy!

---

## 🧪 Testing Payments

For testing DarkPay integration:

1. Use UPI test number: `9999999999@okhdfcbank`
2. Test OTP: `123456`
3. Payment verifies after webhook is received
4. Admin panel shows pending/verified orders

---

## 👥 Contributing

Contributions welcome! Please:
1. Fork the repository
2. Create feature branch: `git checkout -b feature/new-feature`
3. Commit changes: `git commit -am 'Add new feature'`
4. Push to branch: `git push origin feature/new-feature`
5. Submit Pull Request

---

## 📝 License

This project is licensed under the MIT License - see LICENSE file for details.

---

## 🤝 Support

- 📧 Email: luciferofx69@gmail.com
- 🐛 Issues: GitHub Issues
- 💬 Discussions: GitHub Discussions

---

## 🎯 Roadmap

- [ ] Mobile app (React Native)
- [ ] Advanced analytics dashboard
- [ ] Multiple payment gateways (Stripe, PayPal)
- [ ] Subscription system
- [ ] Affiliate program
- [ ] API for third-party integration
- [ ] Email notifications
- [ ] SMS alerts

---

**Built with ❤️ for gamers**
