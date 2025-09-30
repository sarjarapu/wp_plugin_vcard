# Release Management Guide

## Overview

This guide provides comprehensive instructions for managing releases in the Minisite Manager WordPress plugin using our automated semantic versioning system.

## Quick Reference: Release Types

| Release Type | Version Bump | When to Use | Example |
|--------------|--------------|-------------|---------|
| **Patch** | 1.0.0 → 1.0.1 | Bug fixes, docs, dependencies | `./scripts/release.sh patch` |
| **Minor** | 1.0.0 → 1.1.0 | New features (backward compatible) | `./scripts/release.sh minor` |
| **Major** | 1.0.0 → 2.0.0 | Breaking changes | `./scripts/release.sh major` |

## Release Process

### Method 1: Automated Release (Recommended)

#### Prerequisites
- ✅ All tests passing (`composer test`)
- ✅ Code quality checks passing (`composer quality`)
- ✅ No uncommitted changes
- ✅ All commits follow conventional commit format
- ✅ Feature branch merged to main (if applicable)

#### Execute Release
```bash
# 1. Ensure you're on main branch and up to date
git checkout main
git pull origin main

# 2. Run automated release script
./scripts/release.sh patch    # For bug fixes
./scripts/release.sh minor    # For new features
./scripts/release.sh major    # For breaking changes

# 3. The script automatically:
#    - Runs all quality checks
#    - Bumps version in composer.json
#    - Updates plugin header
#    - Generates changelog
#    - Creates git tag
#    - Pushes changes and tags
```

#### Dry Run (Test First)
```bash
# Test the release process without making changes
./scripts/release.sh patch --dry-run
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

## Release Decision Matrix

### When to Make a Patch Release (1.0.0 → 1.0.1)
- ✅ Bug fixes
- ✅ Documentation updates
- ✅ Dependency updates
- ✅ Performance improvements
- ✅ Code refactoring (no behavior change)
- ✅ Security patches

### When to Make a Minor Release (1.0.0 → 1.1.0)
- ✅ New features (backward compatible)
- ✅ New API endpoints
- ✅ Enhanced existing functionality
- ✅ New configuration options
- ✅ New WordPress hooks/filters

### When to Make a Major Release (1.0.0 → 2.0.0)
- ⚠️ Breaking changes to API
- ⚠️ Database schema changes
- ⚠️ Removed deprecated features
- ⚠️ Changed default behavior
- ⚠️ PHP version requirements changed

## Release Checklist

### Before Release
- [ ] All tests passing (`composer test`)
- [ ] Code quality checks passing (`composer quality`)
- [ ] Security scan clean (`composer security`)
- [ ] Documentation updated
- [ ] Changelog reviewed
- [ ] Breaking changes documented
- [ ] Database migrations tested
- [ ] WordPress compatibility verified

### During Release
- [ ] Version bumped correctly
- [ ] Plugin header updated
- [ ] Changelog generated
- [ ] Git tag created
- [ ] Changes pushed to repository

### After Release
- [ ] GitHub release created
- [ ] Release notes published
- [ ] Team notified
- [ ] Monitoring setup
- [ ] Rollback plan ready

## Emergency Hotfix Process

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

## Release Automation

### GitHub Actions Integration

The release process is integrated with GitHub Actions:

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

### Manual Release Trigger

You can trigger releases manually from GitHub Actions:
1. Go to Actions tab
2. Select "Release Pipeline"
3. Click "Run workflow"
4. Choose version type (patch/minor/major)

## Troubleshooting

### Common Release Issues

#### Release Script Fails
```bash
# Check if you're on main branch
git branch --show-current

# Ensure working directory is clean
git status

# Run dry run first
./scripts/release.sh patch --dry-run
```

#### Version Bump Issues
```bash
# Check current version
composer version --no-format

# Verify composer.json is valid
composer validate

# Check for uncommitted changes
git status
```

#### Changelog Generation Issues
```bash
# Test changelog generation
composer changelog

# Check git log format
git log --oneline -10

# Verify conventional commits
git log --grep="^feat\|^fix\|^chore" --oneline -10
```

### Getting Help

1. **Check the logs**: Look at the output from failed commands
2. **Review the guide**: See `docs/implementation/semantic-versioning.md`
3. **Run setup again**: `./scripts/setup-dev.sh`
4. **Check dependencies**: `composer install`

## Best Practices

### Release Frequency
- **Patch releases**: As needed for bug fixes
- **Minor releases**: Monthly or when features are ready
- **Major releases**: Quarterly or when breaking changes accumulate

### Version Naming
- Always use semantic versioning (MAJOR.MINOR.PATCH)
- Tag releases with `v` prefix (e.g., `v1.2.3`)
- Update all version references simultaneously

### Documentation
- Keep changelog up to date
- Document breaking changes clearly
- Maintain API documentation
- Update README with new features

### Testing
- Test releases in staging environment
- Verify database migrations work
- Check WordPress compatibility
- Test plugin activation/deactivation

## Monitoring & Rollback

### Post-Release Monitoring
- Monitor error logs
- Check performance metrics
- Verify user feedback
- Watch for security issues

### Rollback Strategy
- Keep previous version available
- Document rollback procedure
- Test rollback process
- Have rollback triggers ready

---

For more detailed information about semantic versioning and commit conventions, see the [Semantic Versioning Guide](semantic-versioning.md).
