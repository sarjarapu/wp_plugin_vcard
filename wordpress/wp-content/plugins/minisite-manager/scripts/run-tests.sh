#!/bin/bash

# Test runner script for Minisite Manager Plugin
# This script runs comprehensive tests including unit, integration, and coverage
# Use this for manual testing, CI/CD pipelines, or pre-push validation

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

# Check if PHPUnit is available
if [ ! -f "vendor/bin/phpunit" ]; then
    echo -e "${RED} ❌ PHPUnit not found. Please run 'composer install' first.${NC}"
    exit 1
fi

# Run all tests with coverage
echo -e "${CYAN} 🧪 Running all tests with coverage...${NC}"
echo -e "${GRAY} 💡 This includes unit tests, integration tests, and coverage reporting${NC}"
echo ""

# Run phpunit and capture exit code (temporarily disable set -e to allow capture)
# Increase memory limit for coverage generation (HTML reports can be memory-intensive)
set +e
php -d memory_limit=512M vendor/bin/phpunit --testsuite=Unit,Integration --coverage-text --coverage-html=build/coverage/full
PHPUNIT_EXIT_CODE=$?
set -e

# Always run copy command (like finally block)
cp data/styles/custom.css build/coverage/full/_css/custom.css

# Echo completion messages only if PHPUnit succeeded
if [ $PHPUNIT_EXIT_CODE -eq 0 ]; then
    echo -e "${GREEN} 🎉 All tests completed!${NC}"
    echo -e "${GRAY} 📊 Coverage report generated in build/coverage/full/${NC}"
fi

# Exit with phpunit's exit code if it failed, otherwise exit 0
exit ${PHPUNIT_EXIT_CODE:-0}
