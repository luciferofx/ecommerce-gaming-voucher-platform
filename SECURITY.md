# Security Policy

## 🔒 Security Measures Implemented

Dark Gaming Store includes built-in security features:

### 1. Authentication & Authorization
- ✅ **Password Hashing**: bcrypt via PHP's `password_hash()` (cost: 10)
- ✅ **Session Management**: Secure session tokens with user role verification
- ✅ **Admin-Only Access**: Strict role-based access control
- ✅ **Login Rate Limiting**: Prevent brute force attacks

### 2. Database Security
- ✅ **Prepared Statements**: All queries use PDO prepared statements
- ✅ **No SQL Injection**: Parameterized queries throughout
- ✅ **Foreign Keys**: Referential integrity enforced
- ✅ **Minimal Privileges**: DB user has only needed permissions

### 3. Webhook Verification
- ✅ **HMAC-SHA256 Signatures**: All webhooks cryptographically signed
- ✅ **Signature Validation**: Required before processing payments
- ✅ **Provider Support**: Works with Razorpay, Stripe, custom providers
- ✅ **Timing Attack Resistant**: Uses `hash_equals()` comparison

### 4. Data Protection
- ✅ **Sensitive Data Encryption**: Crypto_helper for encryption
- ✅ **No Plain Passwords**: Never logged or stored in plain text
- ✅ **No Credit Cards Stored**: UPI-based payment collection
- ✅ **HTTPS Enforcement**: Redirect HTTP to HTTPS in production

### 5. Input Validation
- ✅ **Type Casting**: All user input type-cast appropriately
- ✅ **Email Validation**: Valid email format required
- ✅ **Amount Validation**: Numeric validation for payment amounts
- ✅ **Enum Validation**: Only valid values accepted for status

---

## 🚨 Reporting Security Issues

**PLEASE DO NOT** open public issues for security vulnerabilities.

Instead, email security concerns to:
```
security@darkgaming.example
```

Include:
1. Description of the vulnerability
2. Steps to reproduce
3. Potential impact
4. Suggested fix (if available)

We will:
- Acknowledge receipt within 24 hours
- Confirm the vulnerability within 72 hours
- Work with you on a fix
- Coordinate disclosure timeline
- Credit you in security advisory (if requested)

---

## 🛡️ Best Practices for Deployment

### Before Going Live

1. **Change Default Admin Password**
   ```php
   // Change from 'admin123' in database immediately
   UPDATE users SET password = PASSWORD_HASH('your-strong-password') WHERE username = 'admin';
   ```

2. **Generate Secure Webhook Secret**
   ```bash
   # Generate 32-character random secret
   php -r "echo bin2hex(random_bytes(16));"
   ```

3. **Enable HTTPS**
   - Use Let's Encrypt (free)
   - Update session cookie settings:
   ```php
   session_set_cookie_params([
       'secure' => true,
       'httponly' => true,
       'samesite' => 'Strict'
   ]);
   ```

4. **Set Proper File Permissions**
   ```bash
   chmod 755 store/ darkpay/
   chmod 644 *.php *.md
   chmod 600 store/includes/db.php  # Most sensitive
   ```

5. **Disable Directory Listing**
   ```apache
   # Add to .htaccess
   <FilesMatch "^\.">
       Order allow,deny
       Deny from all
   </FilesMatch>
   ```

### Ongoing Security

1. **Monitor Logs Regularly**
   - Check for failed authentication attempts
   - Watch for unusual database queries
   - Monitor webhook failures

2. **Update PHP & MySQL**
   - Keep PHP updated to latest version
   - Apply MySQL security patches
   - Monitor for CVEs

3. **Rotate Secrets**
   - Change webhook secret quarterly
   - Rotate database passwords annually
   - Update payment provider API keys when possible

4. **Database Backups**
   - Daily automated backups
   - Test backup restoration monthly
   - Store backups separately from production
   - Encrypt backup storage

5. **Access Control**
   - Limit admin users
   - Use SSH keys (not passwords) for server access
   - Enable two-factor authentication if possible
   - Log all admin actions

---

## 🔧 Security Configuration

### Recommended Environment Setup

```ini
# .env (keep out of version control!)

# Security
SECURE_COOKIES=true
HTTPONLY_COOKIES=true
SAMESITE_COOKIES=Strict

# Error Handling (don't expose in production)
DISPLAY_ERRORS=false
LOG_ERRORS=true
ERROR_LOG=/var/log/darkpay-errors.log

# Session
SESSION_TIMEOUT=3600
SESSION_COOKIE_SECURE=true
SESSION_COOKIE_HTTPONLY=true

# Database
DB_HOST=internal-mysql-host
DB_PORT=3306
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci

# Payment Gateway
DARKPAY_GATEWAY_MODE=live
DARKPAY_VERIFY_SSL=true
DARKPAY_TIMEOUT=30
```

### Webhook Validation Code

```php
// Always verify webhooks before processing
require_once 'includes/darkpay_gateway.php';

$rawBody = file_get_contents('php://input');

// Verify signature first
if (!darkpayVerifyWebhookSignature($rawBody)) {
    http_response_code(401);
    die('Invalid signature');
}

// Then parse and process
$payload = json_decode($rawBody, true);
// ... continue processing
```

---

## 📋 Security Checklist

### Development
- [ ] No hardcoded credentials
- [ ] Use environment variables for secrets
- [ ] Parameterized database queries
- [ ] Input validation on all endpoints
- [ ] Output escaping for HTML
- [ ] No debug output in production

### Deployment
- [ ] HTTPS enabled
- [ ] Secure cookies configured
- [ ] Default credentials changed
- [ ] Webhook secret generated (32+ chars)
- [ ] Database user has minimal privileges
- [ ] Error logging enabled, display disabled
- [ ] Backups configured
- [ ] Monitoring alerts set

### Maintenance
- [ ] Regular security updates applied
- [ ] Logs reviewed weekly
- [ ] Backup restoration tested monthly
- [ ] Secrets rotated quarterly
- [ ] Admin access audited
- [ ] Unusual activities logged
- [ ] Performance monitored

---

## 🚨 Known Vulnerabilities & Mitigations

### Session Fixation
**Mitigation**: Session ID regenerated on login
```php
session_regenerate_id(true);
```

### CSRF Attacks
**Mitigation**: Consider adding CSRF tokens for future versions:
```php
// Generate token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// Validate on form submission
if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die('CSRF token mismatch');
}
```

### XSS Attacks
**Mitigation**: Always escape output
```php
// Escape for HTML context
echo htmlspecialchars($user_input, ENT_QUOTES, 'UTF-8');

// Escape for HTML attributes
echo "data-value=\"" . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . "\"";
```

---

## 📚 Security Resources

- **OWASP Top 10**: https://owasp.org/www-project-top-ten/
- **PHP Security**: https://www.php.net/manual/en/security.php
- **MySQL Security**: https://dev.mysql.com/doc/refman/8.0/en/security.html
- **Payment Card Industry (PCI) DSS**: https://www.pcisecuritystandards.org/
- **Let's Encrypt SSL**: https://letsencrypt.org/

---

## 🔄 Version History

| Version | Date | Security Updates |
|---------|------|-----------------|
| 1.0.0 | 2026-05-20 | Initial release with HMAC webhook verification |

---

## ⚠️ Disclaimer

While this project implements security best practices, no system is 100% secure. When handling payments:

1. **Comply with PCI DSS** - Even if not storing cards
2. **Use HTTPS** - Always encrypt in transit
3. **Monitor actively** - Check logs daily
4. **Keep updated** - Apply security patches immediately
5. **Get professional review** - Consider security audit before major launch
6. **Have incident plan** - Know what to do if compromised

---

**Report security issues responsibly to**: security@darkgaming.example
