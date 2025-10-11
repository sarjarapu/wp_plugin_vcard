#!/bin/bash

# PR Status Check Script
# This script helps you understand and check PR status

echo "🔍 GitHub PR Status Checker"
echo "============================"
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

# Get current branch
CURRENT_BRANCH=$(git branch --show-current)
echo "🌿 Current Branch: $CURRENT_BRANCH"
echo ""

# Check if we're on a PR branch
if [[ "$CURRENT_BRANCH" == "main" || "$CURRENT_BRANCH" == "develop" ]]; then
    echo "ℹ️  You're on the main branch. To check PR status, switch to a feature branch."
    echo ""
    echo "💡 To create a test PR:"
    echo "   git checkout -b test-pr-status"
    echo "   git push origin test-pr-status"
    echo "   # Then create PR on GitHub"
    exit 0
fi

echo "🔗 Quick Links for PR Status:"
echo "============================="
echo ""
echo "📊 Actions (All Workflows):"
echo "   https://github.com/$REPO_OWNER/$REPO_NAME/actions"
echo ""
echo "🔧 Branch Protection Settings:"
echo "   https://github.com/$REPO_OWNER/$REPO_NAME/settings/branches"
echo ""
echo "🔒 Security & Dependencies:"
echo "   https://github.com/$REPO_OWNER/$REPO_NAME/security"
echo ""
echo "📦 Dependency Graph:"
echo "   https://github.com/$REPO_OWNER/$REPO_NAME/network/dependencies"
echo ""

echo "🔍 How to View Detailed Status Checks:"
echo "======================================"
echo ""
echo "1. 📋 In Your PR Page:"
echo "   • Go to your PR: https://github.com/$REPO_OWNER/$REPO_NAME/pull/XX"
echo "   • Scroll down to find 'Checks' section"
echo "   • Click on 'Checks' tab"
echo "   • Click on individual check names for details"
echo ""
echo "2. 🎬 In Actions Tab:"
echo "   • Go to: https://github.com/$REPO_OWNER/$REPO_NAME/actions"
echo "   • Find the workflow run for your PR"
echo "   • Click on the workflow run"
echo "   • Click on individual jobs for logs"
echo ""
echo "3. 💬 In PR Conversation:"
echo "   • Look for status check messages in PR conversation"
echo "   • Click on check names to see details"
echo ""

echo "📊 Expected Status Checks:"
echo "=========================="
echo ""
echo "Current Workflow (.github/workflows/ci.yml):"
echo "  ✅ Test (8.0) - PHPUnit tests on PHP 8.0"
echo "  ✅ Test (8.1) - PHPUnit tests on PHP 8.1"
echo "  ✅ Test (8.2) - PHPUnit tests on PHP 8.2"
echo "  ✅ Test (8.3) - PHPUnit tests on PHP 8.3"
echo ""
echo "Enhanced Workflow (.github/workflows/enhanced-ci.yml):"
echo "  ✅ Dependencies - Dependency checks and security audit"
echo "  ✅ Test (8.0) - PHPUnit tests on PHP 8.0"
echo "  ✅ Test (8.1) - PHPUnit tests on PHP 8.1"
echo "  ✅ Test (8.2) - PHPUnit tests on PHP 8.2"
echo "  ✅ Test (8.3) - PHPUnit tests on PHP 8.3"
echo "  ✅ Code Quality - PHPStan, linting, security"
echo "  ✅ Build Verification - Build and syntax checks"
echo ""

echo "🚨 Troubleshooting:"
echo "==================="
echo ""
echo "If you only see a green merge button without status checks:"
echo "  1. Check if branch protection is enabled"
echo "  2. Verify status checks are required in branch protection"
echo "  3. Check if workflow is actually running in Actions tab"
echo "  4. Ensure workflow triggers on pull_request events"
echo ""
echo "If status checks don't appear:"
echo "  1. Wait a few minutes for checks to start"
echo "  2. Check Actions tab to see if workflow is running"
echo "  3. Verify workflow file syntax is correct"
echo "  4. Check if workflow file is in .github/workflows/"
echo ""

echo "🔧 Enable Enhanced Status Checks:"
echo "================================="
echo ""
echo "To see more detailed dependency checks:"
echo "  1. The enhanced workflow is already created: .github/workflows/enhanced-ci.yml"
echo "  2. Update branch protection to require these status checks:"
echo "     • Dependencies"
echo "     • Test (8.0)"
echo "     • Test (8.1)"
echo "     • Test (8.2)"
echo "     • Test (8.3)"
echo "     • Code Quality"
echo "     • Build Verification"
echo ""

# Check if we have the required files
echo "📁 Checking Workflow Files:"
echo "==========================="
WORKFLOW_FILES=(
    ".github/workflows/ci.yml"
    ".github/workflows/enhanced-ci.yml"
    ".github/workflows/pr-checks.yml"
)

for file in "${WORKFLOW_FILES[@]}"; do
    if [ -f "$file" ]; then
        echo "  ✅ $file"
    else
        echo "  ❌ $file (missing)"
    fi
done

echo ""
echo "💡 Pro Tip:"
echo "==========="
echo "Use the enhanced workflow for more detailed status checks including:"
echo "  • Dependency security audit"
echo "  • Outdated dependency checks"
echo "  • License verification"
echo "  • Build verification"
echo "  • Separate quality checks"
echo ""
echo "This gives you much more visibility into what's being checked!"
