# Git Hooks - Minisite Manager

This document explains the different Git hooks used in the Minisite Manager project and how they work together to ensure code quality.

## Overview

The project uses two types of Git hooks:

1. **Pre-commit Hook** - Runs on every commit (linting & basic tests)
2. **Pre-push Hook** - Runs before pushing to remote (comprehensive testing)

## Pre-commit Hook

**Purpose**: Catch coding standard violations and basic issues before they enter the repository.

**Location**: `.git/hooks/pre-commit` (installed via `scripts/setup-git-hooks.sh`)

**What it checks**:
- ✅ PHP CodeSniffer (WordPress coding standards)
- ✅ PHPStan (static analysis)
- ✅ PHPUnit unit tests (for changes in `src/` directory)

**When it runs**: Every time you run `git commit`

**Installation**:
```bash
composer run setup:hooks
```

**Script**: `scripts/pre-commit-hook.sh`

## Pre-push Hook

**Purpose**: Run comprehensive tests before code reaches the remote repository.

**Location**: `.git/hooks/pre-push` (manual installation)

**What it checks**:
- ✅ All unit tests
- ✅ All integration tests
- ✅ Test coverage (minimum 10%)
- ✅ Performance benchmarks

**When it runs**: Every time you run `git push`

**Manual test**: `composer run test:all` or `./scripts/run-tests.sh`

## Key Differences

| Aspect | Pre-commit Hook | Pre-push Hook |
|--------|----------------|---------------|
| **Trigger** | `git commit` | `git push` |
| **Speed** | Fast (linting only) | Slower (full test suite) |
| **Purpose** | Code quality | Comprehensive validation |
| **Scope** | Staged files only | All code |
| **Tests** | Unit tests only | Unit + Integration + Coverage |
| **Installation** | Automated via script | Manual setup |

## Why Two Hooks?

### Pre-commit Hook Benefits
- **Fast feedback**: Catches issues immediately during development
- **Prevents bad commits**: Stops coding standard violations from entering history
- **Developer productivity**: Quick checks don't slow down workflow
- **AI assistance**: Works with `.cursorrules` to prevent issues

### Pre-push Hook Benefits
- **Comprehensive validation**: Ensures all tests pass before sharing code
- **Coverage enforcement**: Maintains minimum test coverage
- **Integration testing**: Catches issues that unit tests might miss
- **Team confidence**: All pushed code is fully validated

## Workflow

### Typical Development Flow

1. **Make changes** to code
2. **Pre-commit hook runs** automatically on `git commit`
   - Catches linting issues
   - Runs basic unit tests
3. **Fix any issues** if hook fails
4. **Commit succeeds** when all checks pass
5. **Pre-push hook runs** automatically on `git push`
   - Runs full test suite
   - Checks coverage
6. **Push succeeds** when all tests pass

### Bypassing Hooks (Not Recommended)

```bash
# Bypass pre-commit hook
git commit --no-verify -m "fix: emergency hotfix"

# Bypass pre-push hook
git push --no-verify origin main
```

**Warning**: Only use bypassing for emergency situations. Always fix issues in follow-up commits.

## Scripts Overview

### `scripts/pre-commit-hook.sh`
- **Purpose**: Pre-commit validation
- **Usage**: Automatically called by Git
- **Manual run**: Not typically needed

### `scripts/run-tests.sh`
- **Purpose**: Comprehensive testing (pre-push equivalent)
- **Usage**: `composer run test:all` or `./scripts/run-tests.sh`
- **When to use**: Manual testing, CI/CD, pre-push validation

### `scripts/setup-git-hooks.sh`
- **Purpose**: Install pre-commit hook
- **Usage**: `composer run setup:hooks`
- **When to use**: One-time setup or after cloning repository

## Composer Commands

```bash
# Install pre-commit hook
composer run setup:hooks

# Run comprehensive tests (pre-push equivalent)
composer run test:all

# Run all quality checks (pre-commit equivalent)
composer run check

# Run specific test suites
composer run test:unit
composer run test:integration
```

## Configuration

### Pre-commit Hook Configuration
- **Standards**: Defined in `phpcs.xml`
- **Rules**: Defined in `.cursorrules`
- **Customization**: Edit `scripts/pre-commit-hook.sh`

### Pre-push Hook Configuration
- **Coverage threshold**: Edit the hook file directly
- **Test suites**: Configured in `phpunit.xml.dist`
- **Customization**: Edit `.git/hooks/pre-push` (if it exists)

## Troubleshooting

### Pre-commit Hook Issues

**"Hook not running"**
```bash
# Check if hook exists and is executable
ls -la .git/hooks/pre-commit

# Reinstall hook
composer run setup:hooks
```

**"PHPCS not found"**
```bash
# Install dependencies
composer install
```

**"Tests failing"**
```bash
# Run tests manually to see errors
composer run test:unit
```

### Pre-push Hook Issues

**"Hook not found"**
- Pre-push hook is not automatically installed
- Use `composer run test:all` for manual validation

**"Coverage too low"**
```bash
# Check current coverage
composer run test:all

# Add more tests to increase coverage
```

## Best Practices

1. **Always run pre-commit checks** - Don't bypass unless absolutely necessary
2. **Fix issues immediately** - Don't let violations accumulate
3. **Use AI assistance** - The `.cursorrules` file helps prevent issues
4. **Test locally first** - Run `composer run test:all` before pushing
5. **Keep hooks updated** - Reinstall hooks after pulling changes

## Integration with CI/CD

These hooks work alongside your CI/CD pipeline:

- **Pre-commit**: Catches issues early in development
- **Pre-push**: Ensures code quality before sharing
- **CI/CD**: Provides additional checks in deployment pipeline

The hooks reduce the load on CI/CD by catching issues earlier in the development process.

---

## Summary

The dual-hook system provides:

- ✅ **Fast feedback** via pre-commit hooks
- ✅ **Comprehensive validation** via pre-push hooks
- ✅ **Automated enforcement** of coding standards
- ✅ **AI assistance** through `.cursorrules`
- ✅ **Team consistency** across all developers

This ensures high code quality while maintaining developer productivity.
