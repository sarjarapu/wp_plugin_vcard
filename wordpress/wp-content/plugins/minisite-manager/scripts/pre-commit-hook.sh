#!/bin/bash

# Minisite Manager - Pre-commit Hook
# This script runs linting checks before allowing commits

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}🔍 Running pre-commit checks for Minisite Manager...${NC}"

# Get the plugin directory
PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PLUGIN_DIR"

# Check if composer is available
if ! command -v composer &> /dev/null; then
    echo -e "${RED}❌ Composer is not installed or not in PATH${NC}"
    exit 1
fi

# Check if PHPCS is available
if ! command -v vendor/bin/phpcs &> /dev/null; then
    echo -e "${YELLOW}⚠️  PHPCS not found. Installing dependencies...${NC}"
    composer install --no-dev --optimize-autoloader
fi

# Get list of staged PHP files
STAGED_FILES=$(git diff --cached --name-only --diff-filter=ACM | grep -E '\.(php)$' || true)

if [ -z "$STAGED_FILES" ]; then
    echo -e "${GREEN}✅ No PHP files staged for commit${NC}"
    exit 0
fi

echo -e "${BLUE}📁 Checking staged files:${NC}"
echo "$STAGED_FILES" | sed 's/^/  - /'

# Run PHPCS on staged files
echo -e "${BLUE}🔧 Running PHP CodeSniffer...${NC}"

# Create temporary file for PHPCS output
TEMP_OUTPUT=$(mktemp)

# Run PHPCS and capture output
if ! vendor/bin/phpcs --standard=phpcs.xml $STAGED_FILES > "$TEMP_OUTPUT" 2>&1; then
    echo -e "${RED}❌ PHP CodeSniffer found violations:${NC}"
    echo ""
    cat "$TEMP_OUTPUT"
    echo ""
    echo -e "${YELLOW}💡 To fix these issues:${NC}"
    echo -e "  1. Run: ${BLUE}composer lint:fix${NC} to auto-fix some issues"
    echo -e "  2. Review the .cursorrules file for coding guidelines"
    echo -e "  3. Check the enhanced phpcs.xml configuration"
    echo ""
    echo -e "${RED}🚫 Commit blocked due to coding standard violations${NC}"
    rm -f "$TEMP_OUTPUT"
    exit 1
fi

# Clean up
rm -f "$TEMP_OUTPUT"

# Run PHPStan if available
if [ -f "vendor/bin/phpstan" ]; then
    echo -e "${BLUE}🔍 Running PHPStan static analysis...${NC}"
    
    if ! vendor/bin/phpstan analyse --no-progress --error-format=table $STAGED_FILES; then
        echo -e "${RED}❌ PHPStan found issues${NC}"
        echo -e "${RED}🚫 Commit blocked due to static analysis issues${NC}"
        exit 1
    fi
fi

# Run tests if any staged files are in src/ directory
if echo "$STAGED_FILES" | grep -q "^src/"; then
    echo -e "${BLUE}🧪 Running unit tests...${NC}"
    
    if [ -f "vendor/bin/phpunit" ]; then
        if ! vendor/bin/phpunit --no-coverage --stop-on-failure; then
            echo -e "${RED}❌ Unit tests failed${NC}"
            echo -e "${RED}🚫 Commit blocked due to test failures${NC}"
            exit 1
        fi
    fi
fi

echo -e "${GREEN}✅ All pre-commit checks passed!${NC}"
echo -e "${GREEN}🚀 Proceeding with commit...${NC}"

exit 0
