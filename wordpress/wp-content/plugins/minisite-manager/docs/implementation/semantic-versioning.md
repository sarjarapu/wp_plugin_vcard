# Semantic Versioning & Release Automation Guide

## Overview

This guide provides comprehensive best practices and step-by-step instructions for implementing semantic versioning and automated releases in the Minisite Manager WordPress plugin. It includes everything you need to know about version management, release processes, and commit conventions.

## Quick Reference: Semantic Version Prefixes

### Commit Types & Version Impact

| Prefix | Description | Version Bump | Example |
|--------|-------------|--------------|---------|
| `feat:` | New feature for users | **MINOR** | `feat: add user dashboard` |
| `fix:` | Bug fix | **PATCH** | `fix: resolve login timeout` |
| `feat!:` | Breaking change | **MAJOR** | `feat!: redesign API structure` |
| `chore:` | Build/tool changes | **PATCH** | `chore: update dependencies` |
| `docs:` | Documentation only | **PATCH** | `docs: update README` |
| `style:` | Code formatting | **PATCH** | `style: fix indentation` |
| `refactor:` | Code refactoring | **PATCH** | `refactor: simplify auth logic` |
| `perf:` | Performance improvement | **PATCH** | `perf: optimize database queries` |
| `test:` | Add/update tests | **PATCH** | `test: add unit tests for auth` |
| `ci:` | CI/CD changes | **PATCH** | `ci: update GitHub Actions` |
| `build:` | Build system changes | **PATCH** | `build: update webpack config` |
| `revert:` | Revert previous commit | **PATCH** | `revert: undo breaking change` |

### Breaking Change Indicators
- `feat!:` - New feature with breaking changes
- `fix!:` - Bug fix with breaking changes  
- `BREAKING CHANGE:` in commit body
- `!` after any type (e.g., `chore!: remove deprecated API`)

### Scope (Optional)
Add scope in parentheses: `feat(auth): add OAuth2 support`
- `feat(api):` - API-related features
- `fix(db):` - Database-related fixes
- `docs(readme):` - README documentation
- `test(unit):` - Unit tests
- `chore(deps):` - Dependency updates

## 🚀 Quick Start Guide

### Initial Setup (5 minutes)
```bash
# 1. Install dependencies
./scripts/setup-dev.sh

# 2. Verify installation
composer test:unit
composer quality
```

### Daily Development Workflow
```bash
# 1. Create feature branch
git checkout -b feature/new-authentication

# 2. Make changes and commit with conventional format
git add .
git commit -m "feat: add OAuth2 authentication system"

# 3. Push (triggers pre-push hooks automatically)
git push origin feature/new-authentication
```

### Creating Releases (1 command)
```bash
# Patch release (bug fixes)
./scripts/release.sh patch

# Minor release (new features)  
./scripts/release.sh minor

# Major release (breaking changes)
./scripts/release.sh major

# Test first (dry run)
./scripts/release.sh patch --dry-run
```

### Quality Checks
```bash
composer quality    # Run all quality checks
composer lint:fix   # Auto-fix code style
composer analyze    # Static analysis
composer security   # Security scan
```

## Step-by-Step Release Process

### Prerequisites
Before making a release, ensure you have:
- ✅ All tests passing (`composer test`)
- ✅ Code quality checks passing (`composer quality`)
- ✅ No uncommitted changes
- ✅ All commits follow conventional commit format
- ✅ Feature branch merged to main (if applicable)

### Method 1: Automated Release (Recommended)

#### For Patch Releases (Bug Fixes)
```bash
# 1. Ensure you're on main branch and up to date
git checkout main
git pull origin main

# 2. Run automated release script
./scripts/release.sh patch

# 3. The script will:
#    - Run all quality checks
#    - Bump version in composer.json
#    - Update plugin header
#    - Generate changelog
#    - Create git tag
#    - Push changes and tags
```

#### For Minor Releases (New Features)
```bash
./scripts/release.sh minor
```

#### For Major Releases (Breaking Changes)
```bash
./scripts/release.sh major
```

### Method 2: Manual Release Process

#### Step 1: Pre-Release Validation
```bash
# Run all quality checks
composer quality

# Run full test suite
composer test

# Check current version
composer version --no-format
```

#### Step 2: Version Bumping
```bash
# For patch version (1.0.0 → 1.0.1)
composer version patch

# For minor version (1.0.0 → 1.1.0)  
composer version minor

# For major version (1.0.0 → 2.0.0)
composer version major
```

#### Step 3: Generate Changelog
```bash
# Generate changelog from git commits
composer changelog

# Or update existing changelog
php scripts/generate-changelog.php "$(composer version --no-format)"
```

#### Step 4: Create Release
```bash
# Get the new version
NEW_VERSION=$(composer version --no-format)

# Create and push tag
git tag "v$NEW_VERSION"
git push origin "v$NEW_VERSION"

# Push all changes
git push origin main
```

#### Step 5: Create GitHub Release
1. Go to GitHub repository → Releases
2. Click "Create a new release"
3. Select the tag you just created
4. Copy changelog content as release notes
5. Mark as "Latest release" if appropriate
6. Publish release

### Method 3: Dry Run (Testing)
```bash
# Test the release process without making changes
./scripts/release.sh patch --dry-run
```

## Best Practices for Releases

### When to Release

#### Patch Release (1.0.0 → 1.0.1)
- ✅ Bug fixes
- ✅ Documentation updates
- ✅ Dependency updates
- ✅ Performance improvements
- ✅ Code refactoring (no behavior change)

#### Minor Release (1.0.0 → 1.1.0)
- ✅ New features (backward compatible)
- ✅ New API endpoints
- ✅ Enhanced existing functionality
- ✅ New configuration options

#### Major Release (1.0.0 → 2.0.0)
- ⚠️ Breaking changes to API
- ⚠️ Database schema changes
- ⚠️ Removed deprecated features
- ⚠️ Changed default behavior

### Release Checklist

#### Before Release
- [ ] All tests passing
- [ ] Code quality checks passing
- [ ] Security scan clean
- [ ] Documentation updated
- [ ] Changelog reviewed
- [ ] Breaking changes documented
- [ ] Database migrations tested

#### During Release
- [ ] Version bumped correctly
- [ ] Plugin header updated
- [ ] Changelog generated
- [ ] Git tag created
- [ ] Changes pushed to repository

#### After Release
- [ ] GitHub release created
- [ ] Release notes published
- [ ] Team notified
- [ ] Monitoring setup
- [ ] Rollback plan ready

### Emergency Hotfix Process

For critical production issues:

```bash
# 1. Create hotfix branch from main
git checkout main
git pull origin main
git checkout -b hotfix/critical-security-fix

# 2. Make minimal fix
# ... implement fix ...

# 3. Commit with conventional format
git commit -m "fix: resolve critical security vulnerability"

# 4. Run tests
composer test

# 5. Create hotfix release
./scripts/release.sh patch

# 6. Merge back to main
git checkout main
git merge hotfix/critical-security-fix
git push origin main
```

## Current State Analysis

### Strengths
- ✅ PHPUnit testing setup with unit and integration tests
- ✅ Git pre-push hook with coverage validation (20% minimum)
- ✅ Database migration system with semantic versioning
- ✅ Composer dependency management
- ✅ Docker environment for testing
- ✅ Pure PHP changelog generator (no npm dependencies)
- ✅ Automated release scripts
- ✅ Quality gates (linting, static analysis, security)

### Areas for Enhancement
- ✅ Version management automated
- ✅ Release process automated
- ✅ Changelog generation automated
- ✅ Automated tagging implemented

## 1. Version Management Strategy

### Single Source of Truth
- **Primary**: `composer.json` version field
- **Secondary**: Plugin header in `minisite-manager.php`
- **Database**: Migration version tracking (already implemented)

### Version Bump Automation
```bash
# Patch version (bug fixes)
composer version patch

# Minor version (new features)
composer version minor

# Major version (breaking changes)
composer version major
```

### Version Synchronization
All version references should be automatically synchronized:
- `composer.json` → `minisite-manager.php` header
- `composer.json` → `MINISITE_DB_VERSION` constant
- `composer.json` → Database migration target version

## 2. Automated Release Pipeline

### GitHub Actions Workflow
```yaml
# .github/workflows/release.yml
name: Release Pipeline
on:
  push:
    tags:
      - 'v*'
  workflow_dispatch:
    inputs:
      version_type:
        description: 'Version bump type'
        required: true
        default: 'patch'
        type: choice
        options:
        - patch
        - minor
        - major
```

### Release Process Steps

#### Pre-release Validation
1. Run all tests (unit + integration)
2. Check coverage thresholds (target: 80% unit, 60% integration)
3. Static analysis (PHPStan/Psalm level 8)
4. Security scanning
5. WordPress coding standards compliance

#### Version Bumping
1. Update `composer.json`
2. Update plugin header
3. Update database version constant
4. Generate changelog from conventional commits

#### Tagging & Release
1. Create git tag with proper format (`v1.2.3`)
2. Generate release notes from changelog
3. Create GitHub release with artifacts
4. Update WordPress.org (if applicable)

## 3. Enhanced Testing & Quality Gates

### Multi-Stage Testing Strategy

#### Stage 1: Quick Validation
```bash
./vendor/bin/phpunit --testsuite=Unit --stop-on-failure
```

#### Stage 2: Full Test Suite
```bash
./vendor/bin/phpunit --testsuite=Unit,Integration --coverage-text
```

#### Stage 3: Static Analysis
```bash
./vendor/bin/phpstan analyse src --level=8
```

#### Stage 4: Security Scan
```bash
./vendor/bin/security-checker security:check
```

### Coverage Requirements
- **Unit Tests**: 80%+ coverage
- **Integration Tests**: 60%+ coverage
- **Critical Paths**: 95%+ coverage (migrations, repositories)
- **Current Minimum**: 20% (as per pre-push hook)

## 4. Changelog & Documentation Automation

## Commit Message Examples

### Good Commit Messages

#### Features
```bash
# Simple feature
git commit -m "feat: add user dashboard with analytics"

# Feature with scope
git commit -m "feat(auth): implement OAuth2 authentication"

# Feature with breaking change
git commit -m "feat!: redesign user API endpoints

BREAKING CHANGE: All user endpoints now require v2 prefix and return different data structure"
```

#### Bug Fixes
```bash
# Simple bug fix
git commit -m "fix: resolve database connection timeout"

# Bug fix with scope
git commit -m "fix(api): handle null values in user data"

# Bug fix with breaking change
git commit -m "fix!: correct user ID format in API responses

BREAKING CHANGE: User IDs now returned as UUIDs instead of integers"
```

#### Documentation
```bash
# Documentation updates
git commit -m "docs: update installation instructions"
git commit -m "docs(api): add authentication examples"
```

#### Tests
```bash
# Test additions
git commit -m "test: add unit tests for user authentication"
git commit -m "test(integration): add API endpoint tests"
```

#### Chores
```bash
# Dependency updates
git commit -m "chore(deps): update PHPUnit to v11"

# Build system changes
git commit -m "chore(build): update GitHub Actions workflow"

# Code style fixes
git commit -m "style: fix indentation in auth module"
```

#### Performance & Refactoring
```bash
# Performance improvements
git commit -m "perf: optimize database queries in user service"

# Code refactoring
git commit -m "refactor: extract user validation logic to separate class"
```

### Bad Commit Messages (Avoid These)
```bash
# Too vague
git commit -m "fix stuff"
git commit -m "update"
git commit -m "changes"

# No conventional format
git commit -m "Added new feature"
git commit -m "Fixed bug in login"
git commit -m "Updated documentation"

# Too long without body
git commit -m "feat: add comprehensive user management system with authentication, authorization, profile management, settings, preferences, and integration with external services"
```

### Multi-line Commit Messages
For complex changes, use a detailed commit message:

```bash
git commit -m "feat(auth): implement multi-factor authentication

- Add TOTP support for 2FA
- Integrate with SMS providers for backup codes
- Update user settings UI to manage 2FA
- Add security audit logging for auth events

Closes #123
Related to #456"
```

### Commit Message Template
Create a `.gitmessage` template:

```bash
# Create template
cat > ~/.gitmessage << 'EOF'
# <type>(<scope>): <subject>
#
# <body>
#
# <footer>
EOF

# Use template
git config --global commit.template ~/.gitmessage
```

### Automated Changelog Generation
- Parse conventional commits
- Categorize by type (feat, fix, breaking)
- Generate formatted changelog
- Update README.md version references

## 5. WordPress-Specific Considerations

### Plugin Versioning Best Practices
- **WordPress.org compatibility**: Follow WordPress plugin directory requirements
- **Database migrations**: Leverage existing migration system
- **Backward compatibility**: Maintain API compatibility for 2 major versions
- **Deprecation notices**: Use WordPress hooks for deprecation warnings

### WordPress Environment Testing
```bash
# Test against multiple WordPress versions
docker-compose -f docker-compose.test.yml up --build

# Test plugin activation/deactivation
wp plugin activate minisite-manager
wp plugin deactivate minisite-manager
```

### WordPress Coding Standards
```bash
# Install WordPress coding standards
composer require --dev squizlabs/php_codesniffer wp-coding-standards/wpcs

# Run coding standards check
./vendor/bin/phpcs --standard=WordPress src/
```

## 6. Developer Productivity Enhancements

### Development Workflow
```bash
# Daily development
git checkout -b feature/new-authentication
# ... make changes ...
git commit -m "feat: implement OAuth2 authentication"
git push origin feature/new-authentication

# Release preparation
./scripts/release.sh patch  # Create patch release
composer test && composer quality  # Run full test suite
git tag v$(composer version --no-format) && git push --tags  # Create tag and release
```

### Local Development Tools
- **Pre-commit hooks**: Code formatting, linting
- **Development server**: Hot reload for templates
- **Database seeding**: Test data generation
- **Mock services**: External API mocking

### Development Scripts
```json
{
  "scripts": {
    "test": "phpunit",
    "test:unit": "phpunit --testsuite=Unit",
    "test:integration": "phpunit --testsuite=Integration",
    "test:coverage": "phpunit --coverage-html=build/coverage",
    "analyze": "phpstan analyse src --level=8",
    "lint": "phpcs --standard=WordPress src/",
    "fix": "phpcbf --standard=WordPress src/",
    "security": "security-checker security:check",
    "release:prepare": "conventional-changelog -p angular -i CHANGELOG.md -s",
    "release:test": "composer test && composer quality",
    "release:create": "git tag v$(composer version --no-format) && git push --tags"
  }
}
```

## 7. CI/CD Pipeline Architecture

### Branch Strategy
- `main`: Production-ready code
- `develop`: Integration branch
- `feature/*`: Feature development
- `hotfix/*`: Critical fixes

### Pipeline Stages

#### Validation (on every push)
- Code quality checks
- Unit tests
- Security scanning
- WordPress coding standards

#### Integration (on PR to develop)
- Full test suite
- Integration tests
- Performance benchmarks
- Database migration tests

#### Release (on tag creation)
- Production build
- Documentation generation
- Release artifacts
- WordPress.org submission (if applicable)

## 8. Monitoring & Observability

### Release Health Monitoring
- **Error tracking**: Sentry integration
- **Performance monitoring**: New Relic/DataDog
- **Usage analytics**: WordPress hooks for telemetry
- **Rollback capability**: Automated rollback triggers

### Quality Metrics Dashboard
- Test coverage trends
- Code quality scores
- Release frequency
- Bug resolution time
- Performance benchmarks

## Implementation Status

### ✅ Completed (Phase 1 & 2)
- [x] Set up conventional commits
- [x] Implement automated version bumping
- [x] Create basic GitHub Actions workflow
- [x] Add static analysis tools (PHPStan)
- [x] Install WordPress coding standards
- [x] Implement automated changelog generation (Pure PHP)
- [x] Set up release automation
- [x] Add security scanning
- [x] Create development tools and scripts
- [x] Enhance pre-push hook with additional checks
- [x] Remove npm dependencies (Pure PHP solution)

### 🔄 In Progress (Phase 3)
- [ ] Add performance monitoring
- [ ] Implement rollback mechanisms
- [ ] Create quality dashboards
- [ ] Optimize CI/CD pipeline
- [ ] Add WordPress.org integration

### 📋 Future Enhancements
- [ ] Automated WordPress.org plugin submission
- [ ] Advanced monitoring and alerting
- [ ] Automated rollback triggers
- [ ] Performance benchmarking
- [ ] Advanced security scanning

## Available Tools & Commands

### Core Commands
```bash
# Version Management
composer version                    # Show current version
composer version patch             # Bump patch version (1.0.0 → 1.0.1)
composer version minor             # Bump minor version (1.0.0 → 1.1.0)
composer version major             # Bump major version (1.0.0 → 2.0.0)

# Testing
composer test                      # Run all tests
composer test:unit                 # Run unit tests only
composer test:integration          # Run integration tests only
composer test:coverage             # Generate coverage report

# Quality Assurance
composer quality                   # Run all quality checks
composer lint                      # Check code style
composer lint:fix                  # Fix code style issues
composer analyze                   # Run static analysis
composer security                  # Check for security vulnerabilities

# Changelog & Release
composer changelog                 # Generate changelog
./scripts/release.sh patch         # Create patch release
./scripts/release.sh minor         # Create minor release
./scripts/release.sh major         # Create major release
./scripts/release.sh patch --dry-run  # Test release without changes
```

### Development Workflow Commands
```bash
# Setup Development Environment
./scripts/setup-dev.sh             # Setup development environment

# Pre-commit Checks
composer pre-commit                # Run pre-commit quality checks

# Database Operations
composer db:migrate                # Run database migrations
composer db:seed                   # Seed test data
```

### Tool Configuration

#### PHPStan (Static Analysis)
- **Config**: `phpstan.neon`
- **Level**: 8 (strictest)
- **Target**: `src/` directory
- **Excludes**: `tests/`, `vendor/`

#### PHPCS (Code Style)
- **Config**: `phpcs.xml`
- **Standard**: WordPress Coding Standards
- **Target**: `src/` directory
- **Auto-fix**: Available with `composer lint:fix`

#### Security Checker
- **Tool**: Enlightn Security Checker
- **Command**: `composer security`
- **Checks**: Known vulnerabilities in dependencies

### WordPress-Specific Tools
- **WP-CLI**: Command-line interface for WordPress
- **WordPress Coding Standards**: PHPCS with WordPress rules
- **Plugin Boilerplate**: Consistent plugin structure

### Current Dependencies
```json
{
  "require-dev": {
    "brain/monkey": "^2.0",
    "phpunit/phpunit": "^11",
    "phpstan/phpstan": "^1.10",
    "squizlabs/php_codesniffer": "^3.7",
    "wp-coding-standards/wpcs": "^3.0",
    "enlightn/security-checker": "^2.0",
    "cweagans/composer-patches": "^1.7"
  }
}
```

### No Node.js Dependencies
✅ **Pure PHP Solution**: All tools run in PHP environment
- No npm/Node.js required
- Faster CI/CD pipelines
- Consistent development environment
- Reduced complexity

## 11. Configuration Files

### PHPStan Configuration
```yaml
# phpstan.neon
parameters:
  level: 8
  paths:
    - src
  excludePaths:
    - tests
    - vendor
  ignoreErrors:
    - '#Call to an undefined method#'
```

### PHPCS Configuration
```xml
<!-- phpcs.xml -->
<?xml version="1.0"?>
<ruleset name="Minisite Manager">
  <description>WordPress coding standards for Minisite Manager</description>
  
  <file>src</file>
  
  <rule ref="WordPress"/>
  
  <exclude-pattern>*/vendor/*</exclude-pattern>
  <exclude-pattern>*/tests/*</exclude-pattern>
</ruleset>
```

## 12. Best Practices

### Version Management
- Always use semantic versioning (MAJOR.MINOR.PATCH)
- Update all version references simultaneously
- Tag releases immediately after version bump
- Document breaking changes clearly

### Testing
- Write tests before implementing features (TDD)
- Maintain high test coverage
- Test database migrations thoroughly
- Include integration tests for WordPress hooks

### Code Quality
- Follow WordPress coding standards
- Use static analysis tools
- Regular security audits
- Performance monitoring

### Documentation
- Keep changelog up to date
- Document breaking changes
- Maintain API documentation
- Update README with new features

## 13. Troubleshooting

### Common Issues

#### Version Synchronization
- Ensure all version references are updated
- Use automated scripts for version bumping
- Verify plugin header matches composer.json

#### Test Failures
- Check database connection for integration tests
- Verify WordPress environment setup
- Ensure all dependencies are installed

#### Release Process
- Verify all tests pass before tagging
- Check changelog generation
- Ensure GitHub Actions have proper permissions

### Getting Help
- Check existing documentation
- Review GitHub Actions logs
- Consult WordPress developer resources
- Ask team members for assistance

## Related Documentation

- **[Release Management Guide](release.md)** - Detailed release process and procedures
- **[README.md](../../README.md)** - Project overview and setup instructions

---

This guide provides a comprehensive framework for implementing semantic versioning and release automation in your WordPress plugin. The system is now fully implemented and ready to use!

**Quick Links:**
- 🚀 [Quick Start Guide](#-quick-start-guide) - Get up and running in 5 minutes
- 📋 [Step-by-Step Release Process](#step-by-step-release-process) - Detailed release procedures
- 🔧 [Available Tools & Commands](#available-tools--commands) - All available commands
- 📖 [Commit Message Examples](#commit-message-examples) - How to write good commit messages
