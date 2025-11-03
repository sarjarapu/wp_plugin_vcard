# Pre-Submission Checklist & Guide

This document provides a comprehensive checklist and guide for preparing code submissions, PRs, and ensuring all quality checks pass before pushing to GitHub.

## Quick Reference: Commands

### Pre-PR Check (All-in-One)
```bash
composer pr-check
```
Runs all checks that CI runs: security audit, static analysis, code style, and unit tests.

### Individual Checks
```bash
composer audit          # Security audit (vulnerabilities + abandoned packages)
composer analyze        # PHPStan static analysis
composer lint           # PHPCS code style check
composer lint:fix       # Auto-fix code style issues
composer test           # Unit tests
composer test:integration  # Integration tests (if configured)
```

### Git Workflow
```bash
git add -A
git commit -m "TICKET-123: Brief description"
git push
```

---

## Pre-Submission Checklist

### 1. Code Quality Checks

#### ✅ Run Pre-PR Check
```bash
composer pr-check
```
This runs all checks in sequence:
- Security audit (`composer audit`)
- Static analysis (`composer analyze`)
- Code style (`composer lint`)
- Unit tests (`composer test`)

**What it checks:**
- Security vulnerabilities in dependencies
- Abandoned packages (warnings, not errors if transitive)
- PHPStan static analysis errors
- Code style violations (PHPCS)
- Unit test failures

**If it fails:**
- Fix the reported issues
- Run `composer lint:fix` for auto-fixable code style issues
- Re-run `composer pr-check` until all checks pass

---

### 2. Security Audit

#### Purpose
Checks for:
- Known security vulnerabilities in dependencies
- Abandoned packages (warnings for transitive dependencies)

#### Command
```bash
composer audit --no-interaction
```

#### Expected Behavior
- **Exit code 0**: No issues found
- **Exit code 1**: Security vulnerabilities found (must fix)
- **Exit code 2**: Abandoned packages found (warnings, acceptable for transitive deps)

#### Common Issues

**Abandoned Packages:**
- If transitive dependency (e.g., `doctrine/cache` from `doctrine/orm`), acceptable as warning
- CI treats exit code 2 as warning, not error
- Consider upgrading parent package if newer version removes abandoned dependency

**Security Vulnerabilities:**
- Must update vulnerable package or find alternative
- Check package release notes for security patches

---

### 3. Static Analysis (PHPStan)

#### Purpose
Catches type errors, undefined methods, and other static analysis issues before runtime.

#### Command
```bash
composer analyze
```

#### Level
Currently using **level 5** (moderate strictness).

#### Common Issues & Fixes

**Type Mismatches:**
```php
// ❌ Error: Parameter expects AvailableMigrationsSet, AvailableMigrationsList given
// ✅ Fix: Convert using getItems() method
$migrationItems = $availableMigrations->getItems();
$migrationSet = new AvailableMigrationsSet($migrationItems);
```

**Unused Methods:**
```php
// ❌ Error: Method groupConfigs() is unused
// ✅ Fix: Remove the method if truly unused, or mark with @internal if used elsewhere
```

**Offset Issues:**
```php
// ❌ Error: Offset 'description' always exists and is not nullable
// ✅ Fix: Remove unnecessary ?? null check if array key always exists
$description = $config['description']; // Not $config['description'] ?? null
```

**Type Assertions:**
```php
// ❌ Error: Strict comparison using === between string and false
// ✅ Fix: Add explicit type check or phpstan-ignore comment
$decodedKey = base64_decode($key, true);
if ($decodedKey === false || !is_string($decodedKey)) {
    // Handle error
}
```

---

### 4. Code Style (PHPCS)

#### Purpose
Enforces PSR-12 coding standards and WordPress-specific rules.

#### Commands
```bash
composer lint        # Check for style violations
composer lint:fix    # Auto-fix style violations
```

#### Standards Applied
- **PSR-12**: Base PHP coding standards
- **WordPress Security**: Nonce verification, input sanitization, SQL injection prevention
- **Line Length**: 120 characters maximum

#### Common Issues & Fixes

**Line Length Violations:**
```php
// ❌ Line exceeds 120 characters
private function runMigrations(DependencyFactory $dependencyFactory, \Doctrine\Migrations\Metadata\AvailableMigrationsSet $availableMigrations): void

// ✅ Split across multiple lines
private function runMigrations(
    DependencyFactory $dependencyFactory,
    AvailableMigrationsSet $availableMigrations
): void
```

**Auto-Fixable Issues:**
- Brace alignment
- Indentation
- Spacing around operators
- Trailing whitespace

Run `composer lint:fix` to automatically fix these.

**Must Fix Manually:**
- Line length (split long lines)
- Nonce verification (add proper verification)
- Input sanitization (use helper functions)

---

### 5. Unit Tests

#### Command
```bash
composer test
```

#### Coverage
- All unit tests must pass
- Integration tests run separately (if configured)

#### Best Practices
- Write tests before fixing issues
- Test edge cases and error conditions
- Mock external dependencies (database, WordPress functions)

---

### 6. Integration Tests

#### Command
```bash
composer test:integration
# Or directly:
vendor/bin/phpunit --testsuite=Integration
```

#### Requirements
- MySQL Docker container running (for database tests)
- Environment variables set in `phpunit.xml.dist`

#### Database Setup
Tests use a separate MySQL instance (not WordPress database):
- Host: `127.0.0.1:3307` (default)
- Database: `minisite_test`
- User: `minisite` / Password: `minisite`

---

## Git Workflow

### 1. Commit Message Format

#### Standard Format
```
TICKET-123: Brief summary (50 chars max)

Detailed explanation of what changed and why.
- Bullet point for major changes
- Another bullet point if needed
- Keep to 2-3 paragraphs max
```

#### Examples

**Feature Implementation:**
```
MIN-23: Implement database-backed configuration management system

Refactored configuration handling to use helper functions for POST/GET sanitization, following the established pattern from MinisiteEdit feature. Replaced inline isset/wp_unslash calls with dedicated helper methods to improve code consistency and maintainability.

All linting and static analysis checks pass. The configuration system now uses Doctrine ORM for persistence with WordPress table prefix support, in-memory caching, and encryption for sensitive values.
```

**Bug Fix:**
```
Fix PHPStan errors and add pre-PR check script

- Fix type mismatch in DoctrineMigrationRunner (AvailableMigrationsSet)
- Fix ConfigSeeder description offset issue
- Fix ConfigEncryption base64_decode type check
- Add pre-pr-check.sh script to run all CI checks locally
- Add composer pr-check command for easy access
```

**Package Updates:**
```
Upgrade Doctrine packages to latest versions

- Upgrade doctrine/dbal: 3.10.3 → 4.3.4
- Upgrade doctrine/orm: 2.20.7 → 3.5.3
- Upgrade doctrine/migrations: 3.9.4 (latest stable)
- Removed abandoned doctrine/cache dependency (now uses symfony/cache)
- All tests and static analysis passing
```

#### Best Practices
- **Reference Linear ticket** if applicable (e.g., `MIN-23:`)
- **Keep first line under 50 characters** (Git convention)
- **Use imperative mood** ("Add feature" not "Added feature")
- **Explain why**, not just what
- **No file lists** in commit message (Git shows this automatically)

---

### 2. PR Description Generation

#### Template
```markdown
## TICKET-123: Feature/Bug Title

Brief 1-2 sentence summary of what this PR does.

### Key Features / Changes

- **Feature 1**: Description
- **Feature 2**: Description
- **Bug Fix**: Description

### Code Quality Improvements

- Refactored X to use Y pattern (if applicable)
- Improved test coverage (if applicable)
- Fixed linting/static analysis issues (if applicable)

### Testing

- ✅ Unit tests pass
- ✅ Integration tests pass (if applicable)
- ✅ All linting and static analysis checks pass (PHPCS, PHPStan)
- ✅ Security audit passes

### Migration / Breaking Changes

- **None** - or describe what changed and migration steps

### Screenshots / Demo

(Optional - add if UI changes)

### Related Issues

- Closes #123
- Related to #456
```

#### Example PR Description

```markdown
## MIN-23: Database-Backed Configuration Management System

This PR implements a comprehensive configuration management system that stores application settings, API keys, and sensitive data in the WordPress database with proper encryption and caching.

### Key Features

- **Database-backed storage**: Configurations stored in `wp_minisite_config` table with Doctrine ORM
- **Type-safe access**: Support for string, integer, boolean, JSON, and encrypted types
- **Security**: AES-256-GCM encryption for sensitive values (API keys, tokens)
- **In-memory caching**: ConfigManager uses Symfony ArrayAdapter for performance
- **WordPress integration**: Proper table prefix handling via TablePrefixListener
- **Admin UI**: Timber/Twig-based configuration page in WordPress admin

### Code Quality Improvements

**Refactored sanitization helpers**: Replaced inline `isset($_POST[...])` and `wp_unslash()` calls with dedicated helper methods following the established pattern from MinisiteEdit feature:
- `getPostData()` - sanitizes POST text fields
- `getPostDataTextarea()` - sanitizes POST textarea fields  
- `getPostDataArray()` - unslashes POST arrays
- `verifyNonce()` - centralizes nonce verification
- `getGetData()` - sanitizes GET parameters

This improves code consistency, maintainability, and follows WordPress security best practices.

### Testing

- ✅ Unit tests for `ConfigManager` (548 lines)
- ✅ Unit tests for `ConfigEncryption` (242 lines)
- ✅ Integration tests for `ConfigRepository` with real MySQL
- ✅ All linting and static analysis checks pass (PHPCS, PHPStan)

### Migration

The system automatically creates the `wp_minisite_config` table on plugin activation via Doctrine migrations. Default configurations (`openai_api_key`, `pii_encryption_key`, `max_reviews_per_page`) are seeded automatically.

### Breaking Changes

None - this is a new feature that doesn't affect existing functionality.
```

---

## Complete Pre-Push Workflow

### Step-by-Step Process

1. **Make your code changes**

2. **Run pre-PR check:**
   ```bash
   composer pr-check
   ```

3. **Fix any issues:**
   - Code style: `composer lint:fix`
   - Static analysis: Fix PHPStan errors
   - Tests: Fix failing tests

4. **Re-run pre-PR check until all pass:**
   ```bash
   composer pr-check
   ```

5. **Stage changes:**
   ```bash
   git add -A
   ```

6. **Create commit with proper message:**
   ```bash
   git commit -m "TICKET-123: Brief description

   Detailed explanation of changes..."
   ```

7. **Push to branch:**
   ```bash
   git push
   ```

8. **Create PR on GitHub:**
   - Use PR description template above
   - Reference Linear ticket if applicable
   - Add reviewers
   - Link related issues

---

## Why Checks Fail Locally vs CI

### Common Scenarios

#### 1. Line Length Violations
**Why it happens:**
- You run `composer lint` but make changes after
- Different PHPCS configurations
- CI runs with stricter settings

**Solution:**
- Always run `composer pr-check` after final changes
- Run `composer lint:fix` before committing
- Check `phpcs.xml` for line length rules (currently 120 chars)

#### 2. Abandoned Package Warnings
**Why it happens:**
- `pr-check` script treats abandoned packages as warnings (doesn't fail)
- CI runs `composer audit` directly and fails on exit code 2

**Solution:**
- CI workflow updated to handle exit code 2 as warning
- Check if package is transitive dependency (acceptable)
- Consider upgrading parent package to remove abandoned dependency

#### 3. PHPStan Errors
**Why it happens:**
- Code changes introduce new type issues
- PHPStan cache might be stale

**Solution:**
- Run `composer analyze` after code changes
- Clear PHPStan cache: `rm -rf build/phpstan/cache`
- Fix type issues or add appropriate `phpstan-ignore` comments

---

## Helper Functions Pattern

### POST/GET Sanitization

Instead of inline `isset()` and `wp_unslash()` calls, use helper methods:

```php
// ❌ Bad: Inline sanitization
$value = isset($_POST['key']) ? sanitize_text_field(wp_unslash($_POST['key'])) : '';

// ✅ Good: Helper method
private function getPostData(string $key, string $default = ''): string
{
    if (!isset($_POST[$key])) {
        return $default;
    }
    return sanitize_text_field(wp_unslash($_POST[$key]));
}

// Usage
$value = $this->getPostData('key');
```

### Helper Methods to Create

```php
// POST data
private function getPostData(string $key, string $default = ''): string
private function getPostDataTextarea(string $key, string $default = ''): string
private function getPostDataArray(string $key, array $default = []): array
private function getPostDataInt(string $key, int $default = 0): int
private function getPostDataEmail(string $key, string $default = ''): string

// GET data
private function getGetData(string $key, string $default = ''): string

// Nonce verification
private function verifyNonce(string $action, string $nonceField): bool
```

See `src/Features/AppConfig/WordPress/ConfigAdminController.php` for reference implementation.

---

## CI/CD Integration

### GitHub Actions Workflow

The CI pipeline runs these checks automatically:

1. **Dependencies Job:**
   - `composer install`
   - `composer audit` (handles abandoned packages as warnings)

2. **Quality Job:**
   - `composer analyze` (PHPStan)
   - `composer lint` (PHPCS)

3. **Tests Job:**
   - `composer test` (PHPUnit)

### Matching CI Behavior Locally

Run `composer pr-check` to match CI exactly:
- Same commands in same order
- Same exit code handling
- Same error reporting

---

## Troubleshooting

### Issue: `composer pr-check` passes but CI fails

**Possible causes:**
1. Code changes made after running `pr-check`
2. Different PHP/environment versions
3. Stale cache

**Solution:**
- Re-run `composer pr-check` after final changes
- Clear caches: `composer dump-autoload`
- Check PHP version matches CI (8.2)

### Issue: PHPStan errors after package update

**Solution:**
- Check Doctrine ORM/DBAL migration guides
- Update type hints if API changed
- Add `phpstan-ignore` comments for known false positives

### Issue: Tests fail after Doctrine upgrade

**Solution:**
- Check Doctrine migration guide for breaking changes
- Update test setup if EntityManager API changed
- Review Doctrine changelog for deprecated methods

---

## Best Practices Summary

### ✅ DO
- Run `composer pr-check` before every commit
- Use helper functions for POST/GET sanitization
- Reference Linear tickets in commit messages
- Write descriptive PR descriptions
- Fix linting issues with `composer lint:fix`
- Keep commit messages under 50 chars for first line
- Explain "why" in commit messages, not just "what"

### ❌ DON'T
- Skip `composer pr-check` before pushing
- Add `phpcs:ignore` comments without justification
- Use inline `isset($_POST[...])` and `wp_unslash()` directly
- Commit without running tests
- Ignore PHPStan warnings
- Push without fixing linting errors

---

## Quick Command Reference

```bash
# Full pre-PR check (recommended)
composer pr-check

# Individual checks
composer audit          # Security
composer analyze        # PHPStan
composer lint           # PHPCS
composer lint:fix       # Auto-fix style
composer test           # Unit tests

# Git workflow
git add -A
git commit -m "TICKET-123: Description"
git push
```

---

## Related Documents

- [Git Hooks Guide](git-hooks.md) - Setting up pre-commit hooks
- [Coding Standards](coding-standards.md) - Detailed coding guidelines
- [Testing Guide](../testing/) - Testing best practices
- [Logging Best Practices](../logging/logging-best-practices.md) - Logging guidelines

---

**Last Updated:** November 2025  
**Maintained By:** Development Team

