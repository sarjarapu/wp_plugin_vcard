# GitHub Actions Setup for Unit Tests and PR Protection

## Current Status ✅

Your repository already has a comprehensive CI workflow configured at `.github/workflows/ci.yml` that:

- ✅ Runs on pull requests to `main` branch
- ✅ Tests against PHP 8.0, 8.1, 8.2, and 8.3
- ✅ Runs PHPUnit tests (`composer test`)
- ✅ Runs PHPStan analysis (`composer analyze`)
- ✅ Runs code style checks (`composer lint`)
- ✅ Runs security checks (`composer security`)
- ✅ Uploads coverage reports

## Required GitHub Repository Settings

To enable "Require status checks to pass before merging", you need to configure branch protection rules:

### Step 1: Access Repository Settings
1. Go to your GitHub repository: `https://github.com/sarjarapu/wp_plugin_vcard`
2. Click on **Settings** tab
3. Click on **Branches** in the left sidebar

### Step 2: Add Branch Protection Rule
1. Click **Add rule** or **Add branch protection rule**
2. In **Branch name pattern**, enter: `main`
3. Check the following options:

#### Required Status Checks
- ✅ **Require status checks to pass before merging**
- ✅ **Require branches to be up to date before merging**
- In the search box, add these status checks:
  - `test (8.0)`
  - `test (8.1)` 
  - `test (8.2)`
  - `test (8.3)`

#### Additional Protection (Recommended)
- ✅ **Require pull request reviews before merging**
  - Set to `1` required reviewer
- ✅ **Dismiss stale PR approvals when new commits are pushed**
- ✅ **Require review from code owners** (if you have a CODEOWNERS file)
- ✅ **Restrict pushes that create files larger than 100 MB**

### Step 3: Save the Rule
Click **Create** to save the branch protection rule.

## Workflow Triggers

The current workflow triggers on:
```yaml
on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main ]
```

This means:
- ✅ Tests run when PR is created targeting `main`
- ✅ Tests run when new commits are pushed to the PR
- ✅ Tests run when PR is updated
- ✅ Tests run on direct pushes to `main` and `develop`

## Test Commands Available

Your `composer.json` includes these test commands:
- `composer test` - Runs all PHPUnit tests
- `composer test:unit` - Runs only unit tests
- `composer test:integration` - Runs only integration tests
- `composer test:coverage` - Runs tests with coverage report
- `composer analyze` - Runs PHPStan static analysis
- `composer lint` - Runs code style checks
- `composer security` - Runs security vulnerability checks

## Workflow Jobs

The CI workflow runs these jobs in parallel for each PHP version:

1. **Setup Environment**
   - Checkout code
   - Setup PHP with required extensions
   - Cache Composer dependencies

2. **Install Dependencies**
   - `composer install --prefer-dist --no-progress`

3. **Run Tests & Quality Checks**
   - `composer test` (PHPUnit)
   - `composer analyze` (PHPStan)
   - `composer lint` (Code style)
   - `composer security` (Security check)

4. **Upload Coverage** (PHP 8.3 only)
   - Uploads coverage reports to Codecov

## Expected Behavior After Setup

Once branch protection is enabled:

1. **PR Creation**: Tests automatically run
2. **PR Updates**: Tests re-run on new commits
3. **Merge Button**: Will show "Merge pull request" only after all tests pass
4. **Failed Tests**: Merge button will be disabled with error message
5. **Required Reviews**: Additional reviewers must approve before merge

## Troubleshooting

### If Tests Don't Run
- Check if the workflow file is in the correct location: `.github/workflows/ci.yml`
- Verify the branch protection rule is set for the correct branch
- Check if the workflow has the correct trigger conditions

### If Status Checks Don't Appear
- Wait a few minutes for the workflow to complete
- Check the **Actions** tab to see if the workflow is running
- Verify the status check names match exactly in branch protection settings

### If Merge is Still Allowed Despite Failed Tests
- Ensure "Require branches to be up to date before merging" is checked
- Verify all required status checks are listed in branch protection
- Check that the workflow is actually failing (not just running)

## Additional Recommendations

### 1. Add CODEOWNERS File
Create `.github/CODEOWNERS` to automatically request reviews:
```
# Global owners
* @sarjarapu

# PHP files
*.php @sarjarapu

# Tests
tests/ @sarjarapu
```

### 2. Add PR Template
Create `.github/pull_request_template.md` to standardize PR descriptions:
```markdown
## Description
Brief description of changes

## Type of Change
- [ ] Bug fix
- [ ] New feature
- [ ] Breaking change
- [ ] Documentation update

## Testing
- [ ] Unit tests pass
- [ ] Integration tests pass
- [ ] Manual testing completed

## Checklist
- [ ] Code follows style guidelines
- [ ] Self-review completed
- [ ] Documentation updated
```

### 3. Consider Adding More Workflows
- **Dependency Updates**: Dependabot for automatic dependency updates
- **Release Automation**: Automatic version bumping and changelog generation
- **Deployment**: Automatic deployment to staging/production environments

## Summary

Your CI setup is already excellent! You just need to:
1. ✅ Enable branch protection rules in GitHub repository settings
2. ✅ Require the status checks to pass before merging
3. ✅ Optionally add required reviewers

The workflow will then automatically run on every PR and prevent merging until all tests pass.
