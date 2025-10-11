#!/bin/bash

# GitHub Repository Protection Setup Script
# This script helps you set up branch protection and required status checks

echo "🚀 GitHub Repository Protection Setup"
echo "======================================"
echo ""

# Get repository information
REPO_URL=$(git remote get-url origin)
REPO_NAME=$(basename "$REPO_URL" .git)
REPO_OWNER=$(echo "$REPO_URL" | sed -n 's/.*github.com[:/]\([^/]*\)\/.*/\1/p')

echo "📋 Repository Information:"
echo "  Owner: $REPO_OWNER"
echo "  Name: $REPO_NAME"
echo "  URL: https://github.com/$REPO_OWNER/$REPO_NAME"
echo ""

echo "🔧 Manual Setup Required:"
echo "========================="
echo ""
echo "1. Go to: https://github.com/$REPO_OWNER/$REPO_NAME/settings/branches"
echo ""
echo "2. Click 'Add rule' or 'Add branch protection rule'"
echo ""
echo "3. Configure the following:"
echo "   • Branch name pattern: main"
echo "   • ✅ Require status checks to pass before merging"
echo "   • ✅ Require branches to be up to date before merging"
echo ""
echo "4. Add these status checks:"
echo "   • Test (8.0)"
echo "   • Test (8.1)"
echo "   • Test (8.2)"
echo "   • Test (8.3)"
echo "   • Quality Checks"
echo ""
echo "5. Optional but recommended:"
echo "   • ✅ Require pull request reviews before merging (1 reviewer)"
echo "   • ✅ Dismiss stale PR approvals when new commits are pushed"
echo "   • ✅ Require review from code owners"
echo ""
echo "6. Click 'Create' to save the rule"
echo ""

echo "✅ Files Created:"
echo "================="
echo "  • .github/CODEOWNERS - Automatic code owner assignments"
echo "  • .github/pull_request_template.md - Standardized PR template"
echo "  • .github/workflows/pr-checks.yml - Optimized PR workflow"
echo "  • docs/github-actions-setup.md - Detailed setup guide"
echo ""

echo "🧪 Test the Setup:"
echo "=================="
echo "1. Create a test PR to verify workflows run"
echo "2. Check that merge button is disabled until tests pass"
echo "3. Verify status checks appear in PR"
echo ""

echo "📚 For detailed instructions, see: docs/github-actions-setup.md"
echo ""

# Check if we're in a git repository
if [ ! -d ".git" ]; then
    echo "⚠️  Warning: Not in a git repository"
    exit 1
fi

# Check if we have the required files
REQUIRED_FILES=(
    ".github/workflows/ci.yml"
    ".github/CODEOWNERS"
    ".github/pull_request_template.md"
    "composer.json"
)

echo "🔍 Checking required files:"
for file in "${REQUIRED_FILES[@]}"; do
    if [ -f "$file" ]; then
        echo "  ✅ $file"
    else
        echo "  ❌ $file (missing)"
    fi
done

echo ""
echo "🎉 Setup complete! Follow the manual steps above to enable branch protection."
