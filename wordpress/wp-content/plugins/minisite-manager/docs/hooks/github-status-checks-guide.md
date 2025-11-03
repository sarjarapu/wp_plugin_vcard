# GitHub Status Checks and Dependency Checks Guide

## 🔍 How to View Detailed Status Checks

### Method 1: PR Status Checks Section
1. **Go to your PR page** (e.g., `https://github.com/sarjarapu/wp_plugin_vcard/pull/XX`)
2. **Scroll down** to find the **"Checks"** section (usually below the PR description)
3. **Click on "Checks"** tab to see all status checks
4. **Click on individual check names** to see detailed logs

### Method 2: PR Conversation Tab
1. **In the PR conversation tab**, look for status check messages
2. **Click on the check name** (e.g., "Test (8.0)") to see details
3. **View logs** for each individual check

### Method 3: Actions Tab
1. **Go to the "Actions" tab** in your repository
2. **Find the workflow run** for your PR
3. **Click on the workflow run** to see all jobs
4. **Click on individual jobs** to see detailed logs

## 📊 Current Status Check Types

Based on your current CI workflow, you should see these checks:

### Test Checks (Matrix Jobs)
- **Test (8.0)** - PHPUnit tests on PHP 8.0
- **Test (8.1)** - PHPUnit tests on PHP 8.1  
- **Test (8.2)** - PHPUnit tests on PHP 8.2
- **Test (8.3)** - PHPUnit tests on PHP 8.3

Each test check includes:
- ✅ **PHPUnit tests** (`composer test`)
- ✅ **PHPStan analysis** (`composer analyze`)
- ✅ **Code style checks** (`composer lint`)
- ✅ **Security checks** (`composer security`)

## 🔧 Adding Dependency Checks

Currently, your workflow doesn't include explicit dependency checks. Let me add them:

### Option 1: Add to Existing Workflow
Add dependency checks to your current CI workflow.

### Option 2: Create Separate Dependency Check Workflow
Create a dedicated workflow for dependency checks.

### Option 3: Use GitHub's Built-in Dependency Review
Enable GitHub's native dependency review feature.

## 🚀 Recommended: Add Dependency Checks

Let me create an enhanced workflow that includes dependency checks:

### Enhanced CI Workflow Features:
- **Dependency Review** - Check for vulnerable dependencies
- **License Check** - Verify dependency licenses
- **Outdated Dependencies** - Check for outdated packages
- **Security Audit** - Run security checks on dependencies
- **Composer Audit** - Check for known vulnerabilities

## 📋 Status Check Icons and Meanings

### ✅ Green Checkmark
- All checks passed
- Ready to merge (if other requirements met)

### ❌ Red X
- One or more checks failed
- Merge blocked until fixed

### 🟡 Yellow Circle
- Checks are running/in progress
- Wait for completion

### ⚪ Gray Circle
- Checks are pending/queued
- Wait for execution

## 🔍 Troubleshooting Status Checks

### If You Don't See Status Checks:
1. **Check if workflow is running** - Go to Actions tab
2. **Verify branch protection** - Ensure status checks are required
3. **Check workflow syntax** - Look for YAML errors
4. **Verify triggers** - Ensure workflow triggers on PR events

### If Status Checks Don't Appear in PR:
1. **Wait a few minutes** - Checks take time to start
2. **Check Actions tab** - See if workflow is actually running
3. **Verify branch protection settings** - Status checks must be required
4. **Check workflow file location** - Must be in `.github/workflows/`

### If Merge Button Shows Green Despite Failed Checks:
1. **Check branch protection settings** - Ensure "Require status checks" is enabled
2. **Verify status check names** - Must match exactly in branch protection
3. **Check "Require branches to be up to date"** - This prevents bypassing checks
4. **Verify all required checks are listed** - Missing checks won't block merge

## 🎯 Best Practices

### For Repository Owners:
1. **Enable branch protection** with required status checks
2. **Require reviews** from code owners
3. **Dismiss stale approvals** when new commits are pushed
4. **Use CODEOWNERS** for automatic reviewer assignment

### For Developers:
1. **Check status before requesting review** - Ensure all checks pass
2. **Review failed checks** - Fix issues before asking for review
3. **Use PR template** - Provide comprehensive PR descriptions
4. **Test locally** - Run `composer check` before pushing

## 📱 Mobile/App Viewing

### GitHub Mobile App:
- Status checks appear in PR view
- Tap on check names for details
- Limited log viewing capability

### GitHub Desktop:
- Status checks visible in PR view
- Click to open in browser for full details

## 🔗 Quick Links for Your Repository

- **Repository**: https://github.com/sarjarapu/wp_plugin_vcard
- **Actions**: https://github.com/sarjarapu/wp_plugin_vcard/actions
- **Branch Protection**: https://github.com/sarjarapu/wp_plugin_vcard/settings/branches
- **Dependency Graph**: https://github.com/sarjarapu/wp_plugin_vcard/network/dependencies
- **Security**: https://github.com/sarjarapu/wp_plugin_vcard/security

## 🚨 Common Issues and Solutions

### Issue: "Required status check is not set"
**Solution**: Add the exact status check name to branch protection settings

### Issue: "Waiting for status checks to pass"
**Solution**: Wait for all required checks to complete successfully

### Issue: "This branch is out-of-date"
**Solution**: Update your branch with latest changes from main

### Issue: "Required review from code owners"
**Solution**: Request review from users listed in CODEOWNERS file
