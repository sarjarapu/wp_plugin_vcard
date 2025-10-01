# Minisite Manager - Coding Standards & Development Guidelines

This document outlines the coding standards and development guidelines for the Minisite Manager WordPress plugin. These standards ensure code quality, consistency, and maintainability.

## Table of Contents

1. [Overview](#overview)
2. [File Structure](#file-structure)
3. [Naming Conventions](#naming-conventions)
4. [Documentation Standards](#documentation-standards)
5. [Code Style](#code-style)
6. [Security Guidelines](#security-guidelines)
7. [Testing Requirements](#testing-requirements)
8. [Development Tools](#development-tools)
9. [Git Workflow](#git-workflow)
10. [Troubleshooting](#troubleshooting)

## Overview

The Minisite Manager plugin follows WordPress Coding Standards (WPCS) with additional customizations for enhanced code quality. All code must pass automated linting checks before being committed.

### Key Principles

- **Consistency**: All code follows the same patterns and conventions
- **Security**: WordPress security best practices are enforced
- **Documentation**: All public APIs are fully documented
- **Testing**: Business logic is thoroughly tested
- **Maintainability**: Code is clean, readable, and well-structured

## File Structure

### Directory Organization

```
minisite-manager/
├── src/                          # Source code
│   ├── Application/              # Application layer
│   │   ├── Controllers/          # Request handlers
│   │   ├── Http/                 # HTTP-related classes
│   │   └── Rendering/            # View rendering
│   ├── Domain/                   # Business logic
│   │   ├── Entities/             # Domain entities
│   │   ├── Services/             # Domain services
│   │   └── ValueObjects/         # Value objects
│   └── Infrastructure/           # External concerns
│       ├── Persistence/          # Database layer
│       ├── Utils/                # Utility classes
│       └── Versioning/           # Version management
├── tests/                        # Test files
├── templates/                    # Twig templates
├── docs/                         # Documentation
└── scripts/                      # Build and utility scripts
```

### File Naming

- **PHP Files**: Lowercase with hyphens, prefixed with "class-"
  - ✅ `class-rewrite-registrar.php`
  - ❌ `RewriteRegistrar.php`
- **Test Files**: Same as source files with "Test" suffix
  - ✅ `class-rewrite-registrar-test.php`
  - ❌ `RewriteRegistrarTest.php`

## Naming Conventions

### Variables and Functions

All variables, functions, and methods use **snake_case**:

```php
// ✅ Correct
$minisite_id = 123;
$user_data = get_user_data( $user_id );
$version_to_publish = get_latest_version();

// ❌ Incorrect
$minisiteId = 123;
$userData = getUserData( $userId );
$versionToPublish = getLatestVersion();
```

### Classes

Classes use **PascalCase**:

```php
// ✅ Correct
class RewriteRegistrar {
    // Class implementation
}

// ❌ Incorrect
class rewrite_registrar {
    // Class implementation
}
```

### Constants

Constants use **UPPER_SNAKE_CASE**:

```php
// ✅ Correct
const MINISITE_MANAGER_VERSION = '1.0.0';
const DEFAULT_PAGE_SIZE = 20;

// ❌ Incorrect
const minisiteManagerVersion = '1.0.0';
const defaultPageSize = 20;
```

## Documentation Standards

### File Documentation

Every PHP file must have a file doc comment:

```php
<?php
/**
 * File doc comment describing the file's purpose.
 *
 * @package MinisiteManager
 * @since   1.0.0
 */
```

### Class Documentation

Every class must have a class doc comment:

```php
/**
 * Handles URL rewrite registration for minisites.
 *
 * This class manages the registration of custom rewrite rules
 * for minisite URLs and handles the routing logic.
 *
 * @package MinisiteManager
 * @since   1.0.0
 */
class RewriteRegistrar {
    // Class implementation
}
```

### Method Documentation

Every public and protected method must have complete documentation:

```php
/**
 * Registers custom rewrite rules for minisites.
 *
 * @param string $minisite_slug The slug of the minisite.
 * @param array  $rules         Array of rewrite rules to register.
 * @return bool True if registration was successful, false otherwise.
 * @throws InvalidArgumentException When minisite_slug is empty.
 * @since 1.0.0
 */
public function register_rules( $minisite_slug, $rules ) {
    // Method implementation
}
```

### Inline Comments

Inline comments must end with proper punctuation:

```php
// ✅ Correct
// This processes the user data.
// Check if user can edit this minisite!
// Is this a valid minisite?

// ❌ Incorrect
// This processes the user data
// Check if user can edit this minisite
// Is this a valid minisite
```

## Code Style

### Indentation and Spacing

- Use **tabs** for indentation (not spaces)
- Maximum line length: **120 characters**
- One space after comma in function calls
- One space before and after operators

```php
// ✅ Correct
function process_data( $minisite_id, $user_data ) {
    if ( 'active' === $status ) {
        $result = $minisite_id + $user_data;
        return $result;
    }
}

// ❌ Incorrect
function processData($minisiteId,$userData){
    if($status==='active'){
        $result=$minisiteId+$userData;
        return $result;
    }
}
```

### Yoda Conditions

WordPress requires Yoda-style conditionals:

```php
// ✅ Correct
if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
    // Handle POST request
}

if ( 0 === $minisite_id ) {
    // Handle invalid ID
}

// ❌ Incorrect
if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    // Handle POST request
}

if ( $minisite_id === 0 ) {
    // Handle invalid ID
}
```

### Array Formatting

```php
// ✅ Correct
$config = array(
    'minisite_id' => 123,
    'user_data'   => $user_data,
    'status'      => 'active',
);

// ❌ Incorrect
$config = array('minisite_id' => 123, 'user_data' => $user_data, 'status' => 'active');
```

## Security Guidelines

### Input Sanitization

Always sanitize and unslash user input:

```php
// ✅ Correct
$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );
$email = sanitize_email( wp_unslash( $_POST['email'] ) );
$id = absint( wp_unslash( $_POST['id'] ) );

// ❌ Incorrect
$nonce = $_POST['nonce'];
$email = $_POST['email'];
$id = $_POST['id'];
```

### Output Escaping

Always escape output:

```php
// ✅ Correct
echo esc_html( $user_name );
echo esc_url( $minisite_url );
echo esc_attr( $css_class );

// ❌ Incorrect
echo $user_name;
echo $minisite_url;
echo $css_class;
```

### Database Queries

Use WordPress database abstraction:

```php
// ✅ Correct
$results = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}minisites WHERE id = %d",
        $minisite_id
    )
);

// ❌ Incorrect
$results = $wpdb->get_results(
    "SELECT * FROM wp_minisites WHERE id = " . $minisite_id
);
```

### Nonce Verification

Always verify nonces for forms:

```php
// ✅ Correct
if ( ! wp_verify_nonce( $nonce, 'minisite_action' ) ) {
    wp_die( 'Security check failed' );
}

// ❌ Incorrect
// No nonce verification
```

### Capability Checks

Use proper capability checks:

```php
// ✅ Correct
if ( ! current_user_can( 'minisite_edit_profile' ) ) {
    wp_die( 'Insufficient permissions' );
}

// ❌ Incorrect
if ( ! is_admin() ) {
    wp_die( 'Access denied' );
}
```

## Testing Requirements

### Unit Tests

All business logic must have unit tests:

```php
/**
 * Test that invalid minisite ID throws exception.
 *
 * @return void
 */
public function test_should_throw_exception_when_minisite_id_is_invalid() {
    $this->expectException( InvalidArgumentException::class );
    
    $service = new MinisiteService();
    $service->get_minisite( 0 );
}
```

### Integration Tests

WordPress integration must be tested:

```php
/**
 * Test minisite creation with WordPress integration.
 *
 * @return void
 */
public function test_should_create_minisite_in_database() {
    $minisite_data = array(
        'name' => 'Test Minisite',
        'slug' => 'test-minisite',
    );
    
    $minisite_id = $this->minisite_service->create_minisite( $minisite_data );
    
    $this->assertGreaterThan( 0, $minisite_id );
    $this->assertMinisiteExists( $minisite_id );
}
```

## Development Tools

### Required Tools

- **Composer**: Dependency management
- **PHPCS**: Code style checking
- **PHPStan**: Static analysis
- **PHPUnit**: Unit testing
- **Brain Monkey**: WordPress mocking

### Available Commands

```bash
# Install dependencies
composer install

# Run linting
composer lint

# Fix auto-fixable issues
composer lint:fix

# Run static analysis
composer stan

# Run tests
composer test

# Run all checks
composer check
```

### IDE Configuration

#### VS Code

Install these extensions:
- PHP Intelephense
- PHP CS Fixer
- PHP DocBlocker

Add to `.vscode/settings.json`:

```json
{
    "php.suggest.basic": false,
    "php.validate.enable": true,
    "php.validate.executablePath": "/usr/bin/php",
    "phpcs.executablePath": "./vendor/bin/phpcs",
    "phpcs.standard": "phpcs.xml",
    "phpcs.enable": true,
    "phpcs.autoConfigSearch": false
}
```

## Git Workflow

### Pre-commit Hooks

The project includes pre-commit hooks that automatically run:

1. **PHP CodeSniffer**: Checks coding standards
2. **PHPStan**: Static analysis
3. **PHPUnit**: Unit tests (for src/ changes)

### Setting Up Hooks

```bash
# Install pre-commit hook
./scripts/setup-git-hooks.sh

# Remove hook (if needed)
rm .git/hooks/pre-commit
```

### Commit Messages

Use conventional commit format:

```
feat: add minisite creation functionality
fix: resolve nonce verification issue
docs: update API documentation
test: add unit tests for MinisiteService
refactor: improve error handling in controllers
```

## Troubleshooting

### Common Issues

#### "Filenames should be all lowercase"

**Problem**: File names use PascalCase instead of lowercase-hyphenated.

**Solution**: Rename files to use lowercase with hyphens:
```bash
# Rename file
mv RewriteRegistrar.php class-rewrite-registrar.php
```

#### "Variable is not in valid snake_case format"

**Problem**: Variables use camelCase instead of snake_case.

**Solution**: Rename variables:
```php
// Change this
$minisiteId = 123;

// To this
$minisite_id = 123;
```

#### "Missing doc comment for function"

**Problem**: Functions lack proper documentation.

**Solution**: Add complete docblocks:
```php
/**
 * Gets minisite data by ID.
 *
 * @param int $minisite_id The minisite ID.
 * @return array|null Minisite data or null if not found.
 * @since 1.0.0
 */
public function get_minisite_data( $minisite_id ) {
    // Implementation
}
```

#### "Use Yoda Condition checks"

**Problem**: Conditionals don't use Yoda style.

**Solution**: Reverse the comparison:
```php
// Change this
if ( $status === 'active' ) {

// To this
if ( 'active' === $status ) {
```

### Getting Help

1. **Check the .cursorrules file** for AI assistance guidelines
2. **Review phpcs.xml** for specific rule configurations
3. **Run `composer lint`** to see detailed error messages
4. **Use `composer lint:fix`** to auto-fix some issues

### Bypassing Hooks (Not Recommended)

If you need to bypass pre-commit hooks temporarily:

```bash
git commit --no-verify -m "fix: emergency hotfix"
```

**Warning**: Only use this for emergency situations. Always fix linting issues in follow-up commits.

---

## Summary

Following these coding standards ensures:

- ✅ **Consistent code quality** across the project
- ✅ **Enhanced security** through WordPress best practices
- ✅ **Better maintainability** with clear documentation
- ✅ **Automated enforcement** via pre-commit hooks
- ✅ **AI assistance** through .cursorrules configuration

Remember: These standards are enforced automatically. All code must pass linting checks before being committed to the repository.
