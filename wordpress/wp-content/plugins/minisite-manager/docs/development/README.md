# Minisite Manager - Development Setup

This guide helps you set up the development environment for the Minisite Manager WordPress plugin.

## Quick Start

1. **Install dependencies**:
   ```bash
   composer install
   ```

2. **Set up Git hooks** (recommended):
   ```bash
   composer run setup:hooks
   ```

3. **Run quality checks**:
   ```bash
   composer run check
   ```

## Available Commands

### Development
- `composer install` - Install dependencies
- `composer run setup:hooks` - Install pre-commit Git hooks
- `composer run check` - Run all quality checks

### Testing
- `composer test` - Run all tests
- `composer run test:unit` - Run unit tests only
- `composer run test:integration` - Run integration tests only
- `composer run test:all` - Run comprehensive tests with coverage (pre-push equivalent)
- `composer run test:coverage` - Generate test coverage report

### Code Quality
- `composer run lint` - Check coding standards
- `composer run lint:fix` - Auto-fix coding standard issues
- `composer run analyze` - Run static analysis (PHPStan)
- `composer run security` - Check for security vulnerabilities

### Combined Checks
- `composer run quality` - Run linting, analysis, and security checks
- `composer run check` - Run quality checks + unit tests

## Project Structure

```
minisite-manager/
├── .cursorrules              # AI coding guidelines
├── phpcs.xml                 # Enhanced coding standards config
├── src/                      # Source code
├── tests/                    # Test files
├── docs/                     # Documentation
├── scripts/                  # Build and utility scripts
│   ├── pre-commit-hook.sh    # Pre-commit hook script
│   ├── run-tests.sh          # Comprehensive test runner
│   └── setup-git-hooks.sh    # Hook installation script
└── composer.json             # Dependencies and scripts
```

## Key Files

### `.cursorrules`
Contains comprehensive coding guidelines for AI assistance. This file ensures that Cursor AI generates code that follows WordPress standards.

### `phpcs.xml`
Enhanced PHP CodeSniffer configuration with:
- WordPress Coding Standards (WPCS)
- Custom rules for stricter enforcement
- Custom capabilities for the plugin
- Proper exclusions for vendor/tests directories

### Git Hooks
- **Pre-commit Hook**: Automatically runs quality checks before commits
  - PHP CodeSniffer (coding standards)
  - PHPStan (static analysis)
  - PHPUnit (unit tests for src/ changes)
- **Pre-push Hook**: Manual setup for comprehensive testing (see git-hooks.md)

## Development Workflow

1. **Make changes** to source code
2. **Run checks** locally: `composer run check`
3. **Commit changes** (hooks will run automatically)
4. **Fix any issues** that the hooks catch
5. **Push to repository**

## Troubleshooting

### Common Issues

**"Command not found: composer"**
- Install Composer: https://getcomposer.org/download/

**"PHPCS not found"**
- Run: `composer install`

**"Pre-commit hook failed"**
- Fix the issues shown in the error output
- Use `composer run lint:fix` to auto-fix some issues

**"Tests failing"**
- Check the test output for specific failures
- Ensure all dependencies are installed: `composer install`

### Getting Help

1. Check the [Coding Standards Guide](coding-standards.md)
2. Review the `.cursorrules` file for AI assistance guidelines
3. Run `composer run lint` to see detailed error messages
4. Use `composer run lint:fix` to auto-fix some issues

## IDE Setup

### VS Code
Install these extensions:
- PHP Intelephense
- PHP CS Fixer
- PHP DocBlocker

### PhpStorm
- Enable PHP CodeSniffer integration
- Set coding standard to "WordPress"
- Enable PHPStan integration

## Contributing

1. Follow the coding standards outlined in `.cursorrules`
2. Write tests for new functionality
3. Update documentation as needed
4. Ensure all quality checks pass before committing

---

## Additional Documentation

- [Coding Standards](coding-standards.md) - Detailed coding guidelines and standards
- [Git Hooks](git-hooks.md) - Explanation of pre-commit vs pre-push hooks
