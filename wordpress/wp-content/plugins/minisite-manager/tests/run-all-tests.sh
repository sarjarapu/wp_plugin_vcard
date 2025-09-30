#!/bin/bash

# Test runner script for Minisite Manager Plugin
# This script runs the same validation as the git pre-push hook
# Use this for manual testing or CI/CD pipelines

set -e

# Colors for output (optimized for light terminal backgrounds)
RED='\033[0;31m'
GREEN='\033[0;32m'
CYAN='\033[0;36m'
GRAY='\033[0;37m'
BOLD='\033[1m'
NC='\033[0m' # No Color

echo -e "\n${GRAY} 🧪 Running Minisite Manager Plugin Tests...${NC}"

# Get the directory where this script is located
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(dirname "$SCRIPT_DIR")"

# Change to plugin directory
cd "$PLUGIN_DIR"

# Check if git hook exists
GIT_HOOK="$(cd "$PLUGIN_DIR" && find /Users/shyam/Code/digitalxcutives/wordpress-site -name "pre-push" -type f 2>/dev/null | head -1)"
if [ -f "$GIT_HOOK" ]; then
    echo -e "${CYAN} 📋 Found git pre-push hook, executing it...${NC}"
    echo -e "${GRAY} 💡 This will run the same validation as 'git push'${NC}"
    echo ""
    
    # Execute the git hook
    exec "$GIT_HOOK"
else
    echo -e "${CYAN} ⚠️  Git pre-push hook not found at $GIT_HOOK${NC}"
    echo -e "${CYAN} 📋 Running tests directly...${NC}"
    echo ""
    
    # Fallback: run tests directly if hook doesn't exist
    if [ ! -f "vendor/bin/phpunit" ]; then
        echo -e "${RED} ❌ PHPUnit not found. Please run 'composer install' first.${NC}"
        exit 1
    fi
    
    # Run all tests with coverage
    echo -e "${CYAN} 🧪 Running all tests with coverage...${NC}"
    vendor/bin/phpunit --testsuite="Unit,Integration" --coverage-text --coverage-html=build/coverage
    
    echo -e "${GREEN} 🎉 All tests completed!${NC}"
fi
