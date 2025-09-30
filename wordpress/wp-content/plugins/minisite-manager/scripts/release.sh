#!/bin/bash

# Release script for Minisite Manager Plugin
# This script automates the release process with semantic versioning

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
CYAN='\033[0;36m'
GRAY='\033[0;37m'
BOLD='\033[1m'
NC='\033[0m' # No Color

# Configuration
VERSION_TYPE=${1:-patch}
DRY_RUN=${2:-false}

echo -e "${GRAY} 🚀 Starting release process for Minisite Manager Plugin...${NC}"
echo -e "${GRAY} 📋 Version bump type: ${VERSION_TYPE}${NC}"

# Get the plugin directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(dirname "$SCRIPT_DIR")"

# Change to plugin directory
cd "$PLUGIN_DIR"

# Check if we're on main branch
CURRENT_BRANCH=$(git branch --show-current)
if [ "$CURRENT_BRANCH" != "main" ]; then
    echo -e "${RED} ❌ You must be on the main branch to create a release${NC}"
    echo -e "${CYAN} 💡 Current branch: ${CURRENT_BRANCH}${NC}"
    exit 1
fi

# Check if working directory is clean
if ! git diff-index --quiet HEAD --; then
    echo -e "${RED} ❌ Working directory is not clean. Please commit or stash changes.${NC}"
    exit 1
fi

# Run pre-release checks
echo -e "${CYAN} 🧪 Running pre-release validation...${NC}"

# Run tests
if ! composer test; then
    echo -e "${RED} ❌ Tests failed! Cannot proceed with release.${NC}"
    exit 1
fi

# Run quality checks
if ! composer quality; then
    echo -e "${RED} ❌ Quality checks failed! Cannot proceed with release.${NC}"
    exit 1
fi

echo -e "${GREEN} ✅ All pre-release checks passed!${NC}"

# Get current version
CURRENT_VERSION=$(composer version --no-format)
echo -e "${GRAY} 📋 Current version: ${CURRENT_VERSION}${NC}"

# Bump version
echo -e "${CYAN} 📈 Bumping version...${NC}"
if [ "$DRY_RUN" = "true" ]; then
    echo -e "${GRAY} 🔍 DRY RUN: Would bump version to next ${VERSION_TYPE}${NC}"
    NEW_VERSION=$(composer version ${VERSION_TYPE} --dry-run --no-format)
else
    NEW_VERSION=$(composer version ${VERSION_TYPE} --no-format)
fi

echo -e "${GREEN} ✅ Version bumped to: ${NEW_VERSION}${NC}"

# Update plugin header
echo -e "${CYAN} 📝 Updating plugin header...${NC}"
if [ "$DRY_RUN" = "false" ]; then
    sed -i.bak "s/Version:     [0-9]\+\.[0-9]\+\.[0-9]\+/Version:     ${NEW_VERSION}/" minisite-manager.php
    rm minisite-manager.php.bak
fi

# Update database version constant
echo -e "${CYAN} 🗄️  Updating database version...${NC}"
if [ "$DRY_RUN" = "false" ]; then
    sed -i.bak "s/define('MINISITE_DB_VERSION', '[^']*');/define('MINISITE_DB_VERSION', '${NEW_VERSION}');/" minisite-manager.php
    rm minisite-manager.php.bak
fi

# Generate changelog
echo -e "${CYAN} 📋 Generating changelog...${NC}"
if [ "$DRY_RUN" = "false" ]; then
    php scripts/generate-changelog.php "$NEW_VERSION"
fi

# Commit changes
if [ "$DRY_RUN" = "false" ]; then
    echo -e "${CYAN} 💾 Committing changes...${NC}"
    git add .
    git commit -m "chore: release v${NEW_VERSION}"
    
    # Create tag
    echo -e "${CYAN} 🏷️  Creating tag v${NEW_VERSION}...${NC}"
    git tag -a "v${NEW_VERSION}" -m "Release v${NEW_VERSION}"
    
    # Push changes
    echo -e "${CYAN} 🚀 Pushing changes and tag...${NC}"
    git push origin main
    git push origin "v${NEW_VERSION}"
    
    echo -e "${GREEN} 🎉 Release v${NEW_VERSION} completed successfully!${NC}"
    echo -e "${CYAN} 📋 Next steps:${NC}"
    echo -e "${GRAY}   1. Check GitHub Actions for automated release${NC}"
    echo -e "${GRAY}   2. Verify the release on GitHub${NC}"
    echo -e "${GRAY}   3. Update WordPress.org if applicable${NC}"
else
    echo -e "${GRAY} 🔍 DRY RUN completed. No changes were made.${NC}"
fi
