# Push to GitHub - Quick Reference

Complete instructions to push your Dark Gaming Store project to GitHub.

---

## 📋 Prerequisites

1. **GitHub Account** - Create at https://github.com (free)
2. **Git Installed** - Download from https://git-scm.com

### Verify Git Installation
```bash
git --version
```

---

## 🚀 Step-by-Step Guide

### Step 1: Configure Git (First Time Only)

```bash
git config --global user.name "Your Name"
git config --global user.email "your.email@example.com"
```

Verify:
```bash
git config --global --list
```

---

### Step 2: Create Repository on GitHub

1. Go to https://github.com/new
2. **Repository Name**: `dark-gaming-store`
3. **Description**: "Full-stack e-commerce gaming card platform with DarkPay UPI payment gateway"
4. **Public** (for open source) or Private (if preferred)
5. **Skip** "Initialize with README" (we have one)
6. Click **"Create Repository"**

GitHub will show you commands - **Copy the HTTPS URL** (looks like):
```
https://github.com/yourusername/dark-gaming-store.git
```

---

### Step 3: Navigate to Project Directory

```bash
cd c:\xampp\htdocs\dark
```

---

### Step 4: Initialize Git Repository

```bash
git init
```

This creates a `.git` folder (hidden).

---

### Step 5: Add All Files

```bash
git add .
```

**Verify** what will be added:
```bash
git status
```

Should show all project files in green. `.env` should NOT be listed (it's in `.gitignore`).

---

### Step 6: Create First Commit

```bash
git commit -m "Initial commit: Dark Gaming Store with DarkPay payment gateway"
```

---

### Step 7: Connect to GitHub Repository

Replace `YOUR_USERNAME` and `REPO_NAME` with your actual GitHub username and repository name:

```bash
git remote add origin https://github.com/YOUR_USERNAME/dark-gaming-store.git
```

**Verify**:
```bash
git remote -v
```

Should show:
```
origin  https://github.com/YOUR_USERNAME/dark-gaming-store.git (fetch)
origin  https://github.com/YOUR_USERNAME/dark-gaming-store.git (push)
```

---

### Step 8: Rename Branch to Main

```bash
git branch -M main
```

---

### Step 9: Push to GitHub

```bash
git push -u origin main
```

If prompted for password:
- **GitHub Personal Access Token** (recommended)
  1. Go to https://github.com/settings/tokens
  2. Click "Generate new token"
  3. Name: "git-push"
  4. Check: `repo`
  5. Copy token and paste when prompted

---

### Step 10: Verify Upload

1. Go to https://github.com/yourusername/dark-gaming-store
2. You should see all your files!

---

## 📝 Files That Should Be Uploaded

✅ **Should See**:
- README.md
- DEPLOYMENT.md
- SECURITY.md
- CONTRIBUTING.md
- LICENSE
- .gitignore
- .env.example
- darkpay/ (folder with all files)
- store/ (folder with all files)
- .git/ (hidden folder, auto-created)

❌ **Should NOT See**:
- .env (excluded by .gitignore)
- vendor/ (excluded)
- node_modules/ (excluded)
- *.log files

---

## 🔄 Regular Workflow After Initial Push

### After Making Changes

```bash
# 1. Check what changed
git status

# 2. Stage changes
git add .

# 3. Commit with message
git commit -m "[FEATURE] Add multi-currency support for EUR"

# 4. Push to GitHub
git push
```

### Types of Commit Messages

```bash
git commit -m "[FEATURE] Add admin dashboard for payment verification"
git commit -m "[BUGFIX] Fix webhook signature validation"
git commit -m "[DOCS] Update README with deployment instructions"
git commit -m "[REFACTOR] Extract database config to separate file"
```

---

## 🌿 Working with Branches (Advanced)

### Create Feature Branch

```bash
git checkout -b feature/add-email-notifications
# Make changes
git add .
git commit -m "[FEATURE] Add email notifications"
git push origin feature/add-email-notifications
```

Then create Pull Request on GitHub to merge back to main.

### Switch Between Branches

```bash
git checkout main
git checkout feature/other-feature
```

---

## 🆘 Troubleshooting

### "fatal: not a git repository"

You're not in the project directory. Run:
```bash
cd c:\xampp\htdocs\dark
```

### "error: failed to push some refs"

Your local repository is behind. Pull first:
```bash
git pull origin main
# Resolve conflicts if any
git push origin main
```

### "Permission denied (publickey)"

Using SSH instead of HTTPS. Change remote URL:
```bash
git remote set-url origin https://github.com/yourusername/dark-gaming-store.git
git push
```

### ".env accidentally committed"

Remove it:
```bash
git rm --cached .env
git commit -m "Remove .env file"
git push
```

---

## 📤 After Pushing to GitHub

### 1. Add GitHub Topics

1. Go to your repository
2. Click "⚙️ Settings" → "General"
3. Under "Topics", add:
   - `ecommerce`
   - `gaming`
   - `payment-gateway`
   - `php`
   - `upi-payments`
   - `darkpay`

### 2. Add GitHub Description

1. Repository homepage
2. Click "About ⚙️"
3. Add description
4. Add website (if you have one)

### 3. Add Repository Badge to README

Add this to your README.md:

```markdown
[![GitHub license](https://img.shields.io/github/license/yourusername/dark-gaming-store)](LICENSE)
[![GitHub stars](https://img.shields.io/github/stars/yourusername/dark-gaming-store)](https://github.com/yourusername/dark-gaming-store/stargazers)
[![GitHub forks](https://img.shields.io/github/forks/yourusername/dark-gaming-store)](https://github.com/yourusername/dark-gaming-store/network)
```

---

## 🔐 GitHub Settings Recommendations

### Enable Branch Protection

1. Settings → Branches
2. Add rule for `main`
3. Require pull request reviews
4. Require status checks

### Enable Discussions

1. Settings → Features
2. Check "Discussions"
3. Let users ask questions

### Setup GitHub Pages (Optional)

1. Settings → Pages
2. Source: GitHub Actions
3. Will auto-publish README as website

---

## 📊 Monitor Your Repository

After pushing:

1. **GitHub Dashboard** - See commit history
2. **Insights** → **Network** - Visualize commits
3. **Insights** → **Community** - Suggestions for best practices
4. **Issues** - Track bugs and features
5. **Pull Requests** - Review contributions

---

## 💡 Pro Tips

### Add Collaborators

Settings → Collaborators → Add people

### Create Releases

1. Go to "Releases" tab
2. Click "Create a new release"
3. Tag: `v1.0.0`
4. Title: "Version 1.0.0 - Launch"
5. Describe features
6. Auto-generate release notes

### Pin Important Files

Make README more visible:
```markdown
# Dark Gaming Store 🎮

💡 **New here?** Check [Getting Started](DEPLOYMENT.md) guide
🛡️ **Security concerns?** See [Security Policy](SECURITY.md)
🤝 **Want to contribute?** See [Contributing](CONTRIBUTING.md)
```

### Create GitHub Actions Workflow

Create `.github/workflows/php-lint.yml` to auto-check PHP syntax:

```yaml
name: PHP Lint
on: [push, pull_request]
jobs:
  lint:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - uses: php-actions/php-lint@v2
        with:
          php-version: '8.0'
```

---

## 🎯 Your Repository is Live!

Congratulations! Your project is now:
- ✅ Version controlled
- ✅ Backed up on GitHub
- ✅ Publicly visible (if public)
- ✅ Ready for collaboration
- ✅ Easy to deploy from

---

## 📞 Next Steps

1. **Read DEPLOYMENT.md** - Deploy to production
2. **Share the link** - Tell others about your project
3. **Setup Issues** - Let users report bugs
4. **Create Discussions** - Build community
5. **Consider GitHub Pages** - Free website for project

---

**Repository URL**: https://github.com/yourusername/dark-gaming-store

Good luck with your project! 🚀
