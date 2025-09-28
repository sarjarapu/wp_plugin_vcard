# Git Pre-push Hook for Minisite Manager Plugin

This repository includes a Git pre-push hook that automatically runs unit tests, integration tests, and verifies test coverage before allowing code to be pushed to the remote repository.

## What the Hook Does

The pre-push hook performs the following checks:

1. **Unit Tests**: Runs all unit tests in the `tests/Unit` directory
2. **Integration Tests**: Runs all integration tests in the `tests/Integration` directory  
3. **Coverage Check**: Verifies that test coverage is at least 10%

If any of these checks fail, the push will be blocked.

## How It Works

The hook is located at `.git/hooks/pre-push` and will automatically run whenever you attempt to push changes to the remote repository. It:

- Changes to the plugin directory
- Runs PHPUnit with coverage reporting
- Extracts coverage percentage from the output
- Compares coverage against the minimum threshold (10%)
- Blocks the push if tests fail or coverage is insufficient

## Requirements

- PHPUnit must be installed via Composer (`composer install`)
- Tests must be passing
- Coverage must be at least 10%

## Testing the Hook

You can test the hook functionality using the provided test script:

```bash
./test-precommit-hook.sh
```

This will verify that:
- PHPUnit is available and executable
- Tests can be run (even if they currently fail)
- Coverage can be generated

## Bypassing the Hook (Not Recommended)

If you absolutely need to bypass the hook for an emergency push, you can use:

```bash
git push --no-verify origin <branch-name>
```

**Warning**: This should only be used in exceptional circumstances and you should fix any test issues immediately after.

## Troubleshooting

### Tests Are Failing

If tests are failing, the hook will prevent commits. You need to:

1. Run the tests manually to see the specific failures:
   ```bash
   vendor/bin/phpunit --testsuite=Unit
   vendor/bin/phpunit --testsuite=Integration
   ```

2. Fix the failing tests
3. Ensure all tests pass before committing

### Coverage Is Too Low

If coverage is below 60%, you need to:

1. Check current coverage:
   ```bash
   vendor/bin/phpunit --coverage-text --coverage-html=build/coverage
   ```

2. Add more tests to increase coverage
3. Focus on testing uncovered code paths

### Hook Not Running

If the hook doesn't seem to be running:

1. Check if the hook file exists and is executable:
   ```bash
   ls -la .git/hooks/pre-commit
   ```

2. Make sure it's executable:
   ```bash
   chmod +x .git/hooks/pre-commit
   ```

## Configuration

You can modify the minimum coverage requirement by editing the `MIN_COVERAGE` variable in the hook file:

```bash
# Edit the hook file
nano .git/hooks/pre-commit

# Change this line:
MIN_COVERAGE=60
```

## Benefits

This pre-commit hook helps ensure:

- **Code Quality**: All tests must pass before code is committed
- **Test Coverage**: Maintains a minimum level of test coverage
- **Early Detection**: Catches issues before they reach the repository
- **Team Consistency**: All team members follow the same quality standards

## Integration with CI/CD

This hook works alongside your CI/CD pipeline. The hook catches issues early in the development process, while CI/CD provides additional checks in the deployment pipeline.
