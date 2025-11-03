# Running Tests with Code Coverage

## Integration Tests Coverage

### Run Integration Tests with Coverage for Doctrine Migrations

```bash
# Run migration tests with coverage for Doctrine migrations directory
vendor/bin/phpunit tests/Integration/Infrastructure/Migrations/Doctrine/ \
  --coverage-text \
  --coverage-filter src/Infrastructure/Migrations/Doctrine/
```

**Output shows:**
- Test results for all migration tests
- Code coverage for `src/Infrastructure/Migrations/Doctrine/*` files:
  - `Version20251103000000.php`
  - `DoctrineMigrationRunner.php`
  - Any other files in that directory

### Run All Integration Tests with Coverage

```bash
# Run all integration tests with coverage for specific directory
vendor/bin/phpunit --testsuite=Integration \
  --coverage-text \
  --coverage-filter src/Infrastructure/Migrations/Doctrine/
```

### Generate HTML Coverage Report

```bash
# Generate HTML report (opens in browser)
vendor/bin/phpunit tests/Integration/Infrastructure/Migrations/Doctrine/ \
  --coverage-html coverage/doctrine-migrations \
  --coverage-filter src/Infrastructure/Migrations/Doctrine/
```

Then open `coverage/doctrine-migrations/index.html` in your browser.

### Coverage for Specific File

```bash
# Coverage for just one class
vendor/bin/phpunit tests/Integration/Infrastructure/Migrations/Doctrine/ \
  --coverage-text \
  --coverage-filter src/Infrastructure/Migrations/Doctrine/DoctrineMigrationRunner.php
```

## Prerequisites

- **PCOV or Xdebug extension** must be installed and enabled
- Check with: `php -m | grep -i pcov` or `php -m | grep -i xdebug`

If not installed:
```bash
# macOS with Homebrew
pecl install pcov

# Enable in php.ini
extension=pcov.so
```

## Current Coverage Results

Based on latest run:
- **`Version20251103000000`**: 87.88% line coverage (29/33 lines)
- **`DoctrineMigrationRunner`**: Coverage depends on test execution

## Tips

1. **Use `--coverage-filter`** to limit coverage to specific directories/files
2. **Use `--coverage-html`** for visual browsing of coverage
3. **Use `--coverage-clover`** for CI/CD integration (generates XML)

