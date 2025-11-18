# GitHub Actions CI Workflow Analysis

## Overview

This document analyzes the GitHub Actions CI workflow and compares it with local test commands to identify potential failure points.

## GitHub Actions Workflow Structure

The CI workflow (`.github/workflows/ci.yml`) runs **3 parallel jobs** on every pull request:

### 1. Dependencies Job
**Purpose**: Security audit and dependency validation

**Steps (in order)**:
1. ✅ Checkout code
2. ✅ Setup PHP 8.4 with extensions
3. ✅ Cache Composer dependencies
4. ✅ **Install dependencies**: `composer install --prefer-dist --no-progress`
5. ✅ **Security audit**: `composer audit --no-interaction`

**Key Points**:
- Uses PHP 8.4
- Runs from repository root, then `cd wordpress/wp-content/plugins/minisite-manager`
- Uses `--prefer-dist` (no source files, faster install)
- Uses `--no-progress` (cleaner output)

### 2. Test Job
**Purpose**: Run unit and integration tests

**Steps (in order)**:
1. ✅ Checkout code
2. ✅ Setup PHP 8.4 with Xdebug (for coverage)
3. ✅ Setup MySQL 8.0 service (port 3307)
4. ✅ Cache Composer dependencies
5. ✅ **Install dependencies**: `composer install --prefer-dist --no-progress`
6. ✅ Install MySQL client tools
7. ✅ Wait for MySQL to be ready (health check)
8. ✅ **Run unit tests**: `composer test:unit`
9. ✅ **Run integration tests**: `composer test:integration` (with MySQL env vars)

**Key Points**:
- Uses PHP 8.4 with Xdebug enabled
- MySQL service runs on port 3307 (mapped from container port 3306)
- Tests run separately: unit first, then integration
- Integration tests get MySQL connection details via environment variables

### 3. Quality Job
**Purpose**: Code quality checks

**Steps (in order)**:
1. ✅ Checkout code
2. ✅ Setup PHP 8.4 with extensions
3. ✅ Cache Composer dependencies
4. ✅ **Install dependencies**: `composer install --prefer-dist --no-progress`
5. ✅ **Static analysis**: `composer analyze`
6. ✅ **Code style**: `composer lint`

**Key Points**:
- Uses PHP 8.4
- Runs PHPStan and PHPCS checks
- Does NOT auto-fix code (unlike local `pr-check`)

## Local Commands Comparison

### `composer run pr-check`
Runs these checks **sequentially**:
1. Security audit (`composer audit`)
2. Static analysis (`composer analyze`)
3. Code style:
   - Auto-format with PHP CS Fixer (`composer format`)
   - Auto-fix with phpcbf (`composer lint:fix`)
   - Check with PHPCS (`composer lint`)
4. Unit tests (`composer test`)
5. Unit tests with coverage
6. Integration tests with coverage

### `composer run test`
Runs: `bash scripts/run-tests.sh`
- Executes: `phpunit --testsuite=Unit,Integration --coverage-text --coverage-html=build/coverage`
- Runs both test suites together
- Generates coverage reports

## Key Differences

### 1. **PHP Version**
- **GitHub Actions**: PHP 8.4
- **Local**: May be different (check with `php -v`)

**Impact**: PHP 8.4 may have stricter type checking, new deprecations, or different behavior.

### 2. **Test Execution Order**
- **GitHub Actions**:
  - `composer test:unit` (separate)
  - `composer test:integration` (separate)
- **Local**:
  - `composer test` runs both together
  - `composer pr-check` runs `composer test` (both together)

**Impact**: Running tests separately may expose issues with test isolation or shared state.

### 3. **Working Directory**
- **GitHub Actions**:
  - Starts at repository root: `/Users/shyam/Code/digitalxcutives/wordpress-site`
  - Changes to: `wordpress/wp-content/plugins/minisite-manager`
- **Local**:
  - Runs from plugin directory: `/Users/shyam/Code/digitalxcutives/wordpress-site/wordpress/wp-content/plugins/minisite-manager`

**Impact**: Path resolution, autoloader paths, or relative file references may differ.

### 4. **Composer Install Flags**
- **GitHub Actions**: `composer install --prefer-dist --no-progress`
- **Local**: May use `composer install` without flags

**Impact**: `--prefer-dist` installs from dist packages (not source), which might affect:
- Development dependencies
- Patched packages (if using composer-patches)
- Source code availability

### 5. **Code Style Auto-Fixing**
- **GitHub Actions**: Only checks (`composer lint`), does NOT auto-fix
- **Local `pr-check`**: Auto-fixes first (`composer format`, `composer lint:fix`), then checks

**Impact**: If code style issues exist, GitHub Actions will fail, but local might auto-fix them.

### 6. **MySQL Connection**
- **GitHub Actions**:
  - MySQL service container
  - Port 3307 (mapped from container 3306)
  - Health checks before tests
- **Local**:
  - May use different MySQL setup
  - May use different port
  - May have different connection settings

**Impact**: Integration tests may fail if MySQL connection details don't match.

### 7. **Coverage Requirements**
- **GitHub Actions**: Tests run with Xdebug, but coverage is not checked/enforced
- **Local `pr-check`**: Generates coverage reports (may fail if coverage driver missing)

**Impact**: Coverage generation might fail in CI if Xdebug isn't properly configured.

## Most Likely Failure Points

Based on the differences above, here are the most likely causes of CI failures:

### 1. **Code Style Issues** (High Probability)
- GitHub Actions runs `composer lint` without auto-fixing
- If code has style violations, CI will fail
- **Fix**: Run `composer lint` locally and fix all issues

### 2. **PHP 8.4 Compatibility** (Medium Probability)
- PHP 8.4 may have stricter type checking or new deprecations
- **Fix**: Test locally with PHP 8.4 or check CI logs for PHP errors

### 3. **MySQL Connection Issues** (Medium Probability)
- Integration tests may fail if MySQL service isn't ready
- Port or connection details may differ
- **Fix**: Check CI logs for MySQL connection errors

### 4. **Test Isolation Issues** (Low-Medium Probability)
- Running tests separately may expose shared state issues
- **Fix**: Ensure tests are properly isolated, no global state

### 5. **Path/Working Directory Issues** (Low Probability)
- Relative paths or autoloader may behave differently
- **Fix**: Use absolute paths or ensure paths are relative to plugin directory

## Debugging Steps

1. **Check GitHub Actions Logs**:
   - Go to your PR on GitHub
   - Click "Checks" tab
   - Click on failed job (Dependencies, Tests, or Quality)
   - Review error messages

2. **Reproduce Locally**:
   ```bash
   # Test with same PHP version
   php -v  # Check your version

   # Run checks in same order as CI
   cd wordpress/wp-content/plugins/minisite-manager
   composer install --prefer-dist --no-progress
   composer audit --no-interaction
   composer test:unit
   composer test:integration
   composer analyze
   composer lint
   ```

3. **Check for Code Style Issues**:
   ```bash
   composer lint
   # Fix any issues found
   composer lint:fix
   composer format
   ```

4. **Test MySQL Connection**:
   ```bash
   # Ensure MySQL is running and accessible
   mysql -h 127.0.0.1 -P 3307 -u minisite -pminisite minisite_test
   ```

## Next Steps

1. ✅ Review GitHub Actions logs to identify which job is failing
2. ✅ Check specific error messages in failed job
3. ✅ Reproduce the failure locally using the same commands
4. ✅ Fix identified issues
5. ✅ Re-run checks locally before pushing

