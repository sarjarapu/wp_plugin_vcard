#!/bin/bash

# Minisite Manager - Git Hooks Setup Script
# This script installs the pre-commit hook for the project

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}🔧 Setting up Git hooks for Minisite Manager...${NC}"

# Get the plugin directory
PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PLUGIN_DIR"

# Check if we're in a git repository
if [ ! -d ".git" ]; then
    echo -e "${RED}❌ Not in a git repository. Please run this from the project root.${NC}"
    exit 1
fi

# Create hooks directory if it doesn't exist
mkdir -p .git/hooks

# Copy the pre-commit hook
HOOK_SOURCE="scripts/pre-commit-hook.sh"
HOOK_TARGET=".git/hooks/pre-commit"

if [ ! -f "$HOOK_SOURCE" ]; then
    echo -e "${RED}❌ Pre-commit hook script not found at $HOOK_SOURCE${NC}"
    exit 1
fi

cp "$HOOK_SOURCE" "$HOOK_TARGET"
chmod +x "$HOOK_TARGET"

echo -e "${GREEN}✅ Pre-commit hook installed successfully!${NC}"
echo ""
echo -e "${BLUE}📋 What this hook does:${NC}"
echo -e "  • Runs PHP CodeSniffer on staged PHP files"
echo -e "  • Runs PHPStan static analysis (if available)"
echo -e "  • Runs unit tests for changes in src/ directory"
echo -e "  • Blocks commits with coding standard violations"
echo ""
echo -e "${YELLOW}💡 To bypass the hook (not recommended):${NC}"
echo -e "  ${BLUE}git commit --no-verify${NC}"
echo ""
echo -e "${YELLOW}💡 To remove the hook:${NC}"
echo -e "  ${BLUE}rm .git/hooks/pre-commit${NC}"
echo ""
echo -e "${GREEN}🎉 Git hooks setup complete!${NC}"
