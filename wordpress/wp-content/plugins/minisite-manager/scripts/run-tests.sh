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

vendor/bin/phpunit --testsuite=Unit,Integration --coverage-text --coverage-html=build/coverage
cp data/styles/custom.css build/coverage/_css/custom.css

echo -e "${GREEN} 🎉 All tests completed!${NC}"
echo -e "${GRAY} 📊 Coverage report generated in build/coverage/${NC}"
