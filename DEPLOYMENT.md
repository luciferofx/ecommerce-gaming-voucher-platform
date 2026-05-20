# Deployment Guide 🚀

Complete guide to deploy Dark Gaming Store to production with free hosting and MySQL.

---

## 📊 Comparison: Free Hosting Options

| Provider | PHP | MySQL | Storage | Bandwidth | GitHub Deploy | Notes |
|----------|-----|-------|---------|-----------|---|-------|
| **Railway** ⭐ | ✅ | ✅ | Generous | Unlimited | ✅ | Easiest setup, recommended |
| **PlanetScale** | ✅ | ✅ | 5GB | Unlimited | ✅ | MySQL-only, use with Railway |
| **Render** | ✅ | ❌ | 400MB | 100GB | ✅ | Need separate DB |
| **InfinityFree** | ✅ | ✅ | 5GB | Limited | ⚠️ | Simple but slower |
| **000webhost** | ✅ | ✅ | 50GB | 100GB | ✅ | Free with ads |
| **Heroku** | ✅ | ❌ | 512MB | - | ✅ | Free tier discontinued |

---

## 🎯 Recommended Setup: Railway.app + PlanetScale

### Why This?
- ✅ Fastest setup (5 minutes)
- ✅ Automatic GitHub deployments
- ✅ Scalable when you grow
- ✅ Free tier covers most needs
- ✅ Professional infrastructure

### Cost
- **Railway**: Free tier = $5 credits/month (more than enough)
- **PlanetScale**: Free tier = 10GB/month
- **Total**: Completely free for small-medium projects

---

## ⚡ Quick Setup: Railway.app

### Step 1: Create Railway Account

1. Go to https://railway.app
2. Click "Start Project"
3. Sign in with GitHub
4. Authorize Railway

### Step 2: Deploy from GitHub

1. Click "New Project" → "GitHub Repo"
2. Search for your repo `dark-gaming-store`
3. Click "Deploy Now"
4. Wait 2-3 minutes for deployment

### Step 3: Configure MySQL

Railway will auto-create MySQL. Get connection string:

1. Go to Project → "Services"
2. Click "MySQL"
3. Copy connection details from "Connect" tab

Connection string format:
```
HOST: your-railway-project.up.railway.app
PORT: (shown in Railway)
USER: root
PASSWORD: (shown in Railway)
DATABASE: dark_gaming
```

### Step 4: Update Environment Variables

1. In Railway project → "Variables"
2. Add these variables:

```
DB_HOST=your-railway-host
DB_PORT=3306
DB_USER=root
DB_PASSWORD=your-password
DB_NAME=dark_gaming

DARKPAY_UPI_VPA=yourmerchant@bank
DARKPAY_MERCHANT_NAME=Dark Gaming Store
DARKPAY_WEBHOOK_SECRET=your-secret-key-32-chars-min
```

### Step 5: Initialize Database

Run SQL import on Railway MySQL:

```bash
mysql -h your-host -P 3306 -u root -p dark_gaming < store/dark_gaming.sql
```

Or use Railway's CLI:
```bash
railway connect mysql
# Then paste contents of store/dark_gaming.sql
```

### Step 6: Get Your URL

Railway will show you a public URL like:
```
https://dark-gaming-store-production.up.railway.app
```

Use this as your `SITE_URL` in `.env`

---

## 🔗 Alternative: PlanetScale (MySQL Only)

### Step 1: Create PlanetScale Account

1. Go to https://planetscale.com
2. Sign up with GitHub
3. Create new database "dark_gaming"

### Step 2: Get Connection String

1. Database → "Connect"
2. Select "PHP/PDO"
3. Copy connection string

Example:
```
mysql://user:password@aws.connect.psdb.cloud/dark_gaming?sslmode=require
```

### Step 3: Update db.php

```php
// store/includes/db.php
$dsn = 'mysql:host=aws.connect.psdb.cloud;port=3306;dbname=dark_gaming;ssl_verifycertname=0;ssl_verifyservercert=0';
$user = 'your_planetscale_user';
$password = 'your_planetscale_password';
```

### Step 4: Import Schema

```bash
mysql -h aws.connect.psdb.cloud -u user -p dark_gaming < store/dark_gaming.sql
```

---

## 🌐 Simple Setup: InfinityFree

### Step 1: Create Account

1. Go to https://www.infinityfree.net
2. Sign up with email
3. Create new account

### Step 2: Create Database

1. Login to cPanel
2. Go to "MySQL Databases"
3. Create database: `dark_gaming`
4. Create user with password
5. Grant all privileges

### Step 3: Upload Files

1. Use File Manager or FTP:
   - Host: provided by InfinityFree
   - User: provided
   - Password: provided

2. Upload entire `dark` folder to `public_html/`

### Step 4: Configure db.php

```php
// store/includes/db.php
$host = 'localhost';
$db = 'if0_xxxxx_dark_gaming';  // InfinityFree format
$user = 'if0_xxxxx_user';
$password = 'your_password';
```

### Step 5: Import Database

Use cPanel → phpMyAdmin:
1. Select database
2. Go to "Import"
3. Upload `store/dark_gaming.sql`
4. Click "Go"

### Step 6: Access Your Site

```
http://yourdomain.rf.gd/
```

---

## 🔐 Production Checklist

Before going live:

- [ ] Update `.env` with production values
- [ ] Set `DARKPAY_GATEWAY_MODE=live`
- [ ] Configure webhook URL to production domain
- [ ] Enable HTTPS (SSL certificate)
- [ ] Set secure session cookies
- [ ] Update admin password (change from `admin123`)
- [ ] Enable error logging (not display)
- [ ] Set `SESSION_COOKIE_SECURE=true`
- [ ] Test payment webhook
- [ ] Setup email notifications
- [ ] Enable database backups
- [ ] Setup monitoring/alerts

---

## 🔗 Connect Your Domain

### Custom Domain (Example: Namecheap)

1. Go to your domain registrar
2. Edit DNS records
3. Update `A` record to your hosting provider's IP
4. Update `CNAME` for `www` if needed

**Railway Example:**
```
A Record: 146.190.x.x (Railway's IP)
CNAME:    railway.app (for www subdomain)
```

### SSL Certificate

- **Railway**: Auto-included
- **PlanetScale**: Standard SSL included
- **InfinityFree**: Auto SSL (free)

---

## 📊 Monitoring & Maintenance

### Daily Tasks
- Check order processing
- Monitor payment failures
- Review user feedback

### Weekly Tasks
- Review admin dashboard stats
- Check application logs
- Verify backups

### Monthly Tasks
- Update PHP packages
- Review security logs
- Analyze payment trends
- Backup database

---

## 🆘 Troubleshooting

### Database Connection Failed

**Problem**: `SQLSTATE[HY000]`

**Solutions**:
1. Check hostname is correct
2. Verify username/password
3. Ensure database exists
4. Check firewall rules (Railway/PlanetScale allow external)

### Webhook Not Receiving Payments

**Problem**: Orders stay "pending" despite payment

**Solutions**:
1. Verify webhook URL is correct
2. Check webhook secret matches
3. Review logs for signature errors
4. Test with payment provider's webhook tester
5. Ensure domain is publicly accessible

### PHP Extensions Missing

**Problem**: `Call to undefined function...`

**Solutions**:
- Check `php -m` for extensions
- Enable in `php.ini`
- Contact hosting provider if not available

### Database Size Limit

**Problem**: `Allowed memory exceeded`

**Solutions**:
- Archive old orders
- Clean up logs
- Optimize tables
- Upgrade hosting plan

---

## 📈 Scaling Tips

### When You Grow

1. **Upgrade Railway plan** - Pay-as-you-go pricing
2. **Optimize queries** - Add indexes to orders table
3. **Cache data** - Cache exchange rates longer
4. **Add CDN** - CloudFlare (free)
5. **Monitor performance** - Use Railway logs

### Database Optimization

```sql
-- Add indexes for frequently queried columns
ALTER TABLE orders ADD INDEX idx_user_id (user_id);
ALTER TABLE orders ADD INDEX idx_status (status);
ALTER TABLE cards ADD INDEX idx_status (status);

-- Archive old orders (keep last 2 years)
-- First backup, then:
-- DELETE FROM orders WHERE DATE(purchase_date) < DATE_SUB(NOW(), INTERVAL 2 YEAR);
```

---

## 📚 Additional Resources

- **Railway Docs**: https://docs.railway.app
- **PlanetScale Docs**: https://planetscale.com/docs
- **PHP PDO**: https://www.php.net/manual/en/book.pdo.php
- **MySQL Docs**: https://dev.mysql.com/doc/

---

## 💬 Need Help?

- Check our [README.md](README.md)
- Open a GitHub Issue
- Check [Discussions](../../discussions)
- Email: luciferofx69@gmail.com

---

**Deployment Time**: ~15 minutes with Railway 🚀
