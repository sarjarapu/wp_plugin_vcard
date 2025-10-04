# TODO: Move Pre-Push Validations to GitHub Actions

## Status: Temporarily Disabled for Local Development

To speed up local development, the following validations have been temporarily disabled in the pre-push hook:

### Disabled Validations

1. **Integration Tests** (saves ~1-1.5 minutes)
   - Currently commented out in: `/Users/shyam/Code/digitalxcutives/wordpress-site/.git/hooks/pre-push`
   - Lines 126-130

2. **Coverage Checks** (saves ~30 seconds)
   - Currently commented out in: `/Users/shyam/Code/digitalxcutives/wordpress-site/.git/hooks/pre-push`
   - Lines 134-156

### Impact

- **Before**: Git push took ~2 minutes
- **After**: Git push takes ~30 seconds (only Unit tests run)

### Action Items

☐ Create GitHub Actions workflow for Pull Requests that includes:
   - [ ] Unit tests
   - [ ] Integration tests
   - [ ] Coverage checks (minimum 40%)
   - [ ] Code quality checks (PHPCS, PHPStan)

☐ Configure GitHub branch protection rules to require:
   - [ ] All tests passing
   - [ ] Coverage threshold met
   - [ ] Code review approval

### Workflow File Location

Create: `.github/workflows/pr-validation.yml`

### Sample Workflow Structure

```yaml
name: PR Validation

on:
  pull_request:
    branches: [ main, develop ]

jobs:
  tests:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
      - name: Install Dependencies
        run: composer install
      - name: Run Unit Tests
        run: vendor/bin/phpunit --testsuite=Unit
      - name: Run Integration Tests
        run: vendor/bin/phpunit --testsuite=Integration
      - name: Check Coverage
        run: vendor/bin/phpunit --coverage-text --coverage-html=build/coverage
```

### Re-enabling Local Validations

When GitHub Actions are set up, you can re-enable local validations by:

1. Edit: `/Users/shyam/Code/digitalxcutives/wordpress-site/.git/hooks/pre-push`
2. Uncomment lines 126-130 (Integration Tests)
3. Uncomment lines 134-156 (Coverage Checks)

---

**Date Disabled**: October 4, 2025
**Reason**: Speed up local development workflow
**Target Date for GitHub Actions**: TBD

