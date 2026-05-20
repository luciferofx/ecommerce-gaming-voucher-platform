# Contributing to Dark Gaming Store

Thank you for your interest in contributing to Dark Gaming Store! We welcome contributions from the community.

## Getting Started

1. **Fork the repository** on GitHub
2. **Clone your fork** locally:
   ```bash
   git clone https://github.com/yourusername/dark-gaming-store.git
   cd dark-gaming-store
   ```
3. **Create a feature branch**:
   ```bash
   git checkout -b feature/your-feature-name
   ```

## Development Setup

### Prerequisites
- PHP 7.4+
- MySQL 5.7+
- Composer (optional)

### Installation
1. Copy `.env.example` to `.env`:
   ```bash
   cp .env.example .env
   ```
2. Update `.env` with your local database credentials
3. Import the database schema:
   ```bash
   mysql -u root -p dark_gaming < store/dark_gaming.sql
   ```
4. Start the PHP development server:
   ```bash
   php -S localhost:8000
   ```

## Code Standards

### PHP
- Follow PSR-12 coding standards
- Use 4 spaces for indentation
- Always use prepared statements (PDO)
- Never commit database passwords to version control
- Add comments for complex logic

### JavaScript
- Use vanilla JS (no jQuery required)
- Use const/let instead of var
- Use meaningful variable names
- Comment complex functions

### HTML/CSS
- Use semantic HTML5
- Follow BEM naming convention for CSS classes
- Mobile-first responsive design
- Accessible color contrasts (WCAG AA minimum)

### Examples

**Good PHP Code:**
```php
<?php
// Fetch user's orders with proper prepared statement
$stmt = $pdo->prepare("
    SELECT o.id, o.status, c.game_name 
    FROM orders o 
    JOIN cards c ON o.card_id = c.id 
    WHERE o.user_id = :user_id
");
$stmt->execute([':user_id' => $userId]);
$orders = $stmt->fetchAll();
```

**Good JavaScript Code:**
```javascript
// Toggle payment method visibility
function togglePaymentMethod(method) {
    const container = document.getElementById(`${method}-container`);
    if (container) {
        container.classList.toggle('hidden');
    }
}
```

## Commit Messages

Write clear, descriptive commit messages:

```
Format: [TYPE] Brief description (50 chars max)

[FEATURE] Add multi-currency support
[BUGFIX] Fix webhook signature validation error
[DOCS] Update database schema documentation
[REFACTOR] Extract DarkPay config to separate file
```

### Types
- `[FEATURE]` - New functionality
- `[BUGFIX]` - Bug fixes
- `[DOCS]` - Documentation updates
- `[REFACTOR]` - Code refactoring
- `[TEST]` - Testing additions
- `[STYLE]` - CSS/styling changes
- `[CHORE]` - Maintenance tasks

## Pull Request Process

1. **Update the README.md** if you add new features
2. **Test your changes** thoroughly:
   - Test on multiple browsers (Chrome, Firefox, Safari, Edge)
   - Test both logged-in and guest scenarios
   - Test admin functionalities
3. **Ensure no environment files are committed**:
   - `.env` should never be committed
   - Only `.env.example` should be in repo
4. **Write a clear PR description**:
   ```markdown
   ## Description
   Brief description of changes

   ## Related Issues
   Fixes #123

   ## Testing
   - [ ] Tested locally
   - [ ] Tested on mobile
   - [ ] Tested with different browsers

   ## Changes
   - Change 1
   - Change 2
   ```

## Reporting Bugs

When reporting bugs, please include:
1. **Description**: Clear description of the bug
2. **Steps to Reproduce**:
   - Step 1
   - Step 2
   - Step 3
3. **Expected Behavior**: What should happen
4. **Actual Behavior**: What actually happened
5. **Environment**:
   - PHP version
   - MySQL version
   - Browser (if relevant)
6. **Screenshots/Logs**: If applicable

### Example Bug Report
```
Title: Payment webhook not processing UPI transactions

Description:
When a UPI payment is made, the webhook is received but order status is not updated.

Steps to Reproduce:
1. Login as user
2. Purchase a gaming card via UPI
3. Complete payment in test UPI app
4. Check order status in dashboard

Expected: Order status should show "Verified"
Actual: Order status remains "Pending"

Environment:
- PHP 8.0
- MySQL 8.0
- Using Railway.app deployment
```

## Feature Requests

To suggest a new feature:
1. Check existing issues first
2. Create new issue with label `enhancement`
3. Describe the feature and why it would be useful
4. Provide examples if possible

## Project Structure

Keep in mind when contributing:

```
dark/
├── darkpay/           # Payment gateway - Modify webhook handlers carefully
├── store/             # Main application - Follow existing patterns
├── README.md          # Update if adding features
├── .env.example       # Update if adding env variables
└── LICENSE
```

## Security Considerations

When contributing, please ensure:
- ✅ No SQL injection vulnerabilities (use prepared statements)
- ✅ No exposed API keys or secrets in code
- ✅ Webhooks are properly signed and verified
- ✅ User input is validated and sanitized
- ✅ Admin-only pages check user role
- ✅ Sensitive data is not logged

## Performance Guidelines

- Keep database queries efficient (use indexes)
- Cache exchange rates and static data
- Minimize API calls
- Optimize images and assets
- Load JavaScript asynchronously where possible

## Documentation

- Document new features in README.md
- Add inline comments for complex logic
- Update API endpoints documentation
- Include configuration examples
- Document environment variables in `.env.example`

## Questions?

- Open a Discussion on GitHub
- Email: support@darkgaming.example
- Check existing issues for answers

---

Thank you for contributing to Dark Gaming Store! 🎮
