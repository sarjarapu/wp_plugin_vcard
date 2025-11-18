#!/bin/bash

# Development setup script for Minisite Manager Plugin
# This script sets up the development environment with all necessary tools

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
CYAN='\033[0;36m'
GRAY='\033[0;37m'
BOLD='\033[1m'
NC='\033[0m' # No Color

echo -e "${GRAY} 🛠️  Setting up development environment for Minisite Manager Plugin...${NC}"

# Get the plugin directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(dirname "$SCRIPT_DIR")"

# Change to plugin directory
cd "$PLUGIN_DIR"

# Check if composer is available
if ! command -v composer &> /dev/null; then
    echo -e "${RED} ❌ Composer is not installed. Please install Composer first.${NC}"
    exit 1
fi

# Install PHP dependencies
echo -e "${CYAN} 📦 Installing PHP dependencies...${NC}"
composer install

# Note: No Node.js dependencies needed - using pure PHP solution
echo -e "${GREEN} ✅ Using pure PHP solution - no Node.js required${NC}"

# Set up PHPCS WordPress coding standards
echo -e "${CYAN} 🔧 Setting up WordPress coding standards...${NC}"
if [ -f "vendor/bin/phpcs" ]; then
    vendor/bin/phpcs --config-set installed_paths vendor/wp-coding-standards/wpcs
    echo -e "${GREEN} ✅ WordPress coding standards configured${NC}"
else
    echo -e "${GRAY} ⚠️  PHPCS not found. Run 'composer install' first.${NC}"
fi

# Create build directory
echo -e "${CYAN} 📁 Creating build directories...${NC}"
mkdir -p build/coverage/full
mkdir -p build/coverage/unit
mkdir -p build/coverage/integration
mkdir -p build/logs

# Set up git hooks (if not already set up)
echo -e "${CYAN} 🪝 Checking git hooks...${NC}"
GIT_HOOKS_DIR="$(git rev-parse --git-dir)/hooks"
if [ ! -f "$GIT_HOOKS_DIR/pre-push" ]; then
    echo -e "${GRAY} ⚠️  Pre-push hook not found. Please set up git hooks manually.${NC}"
else
    echo -e "${GREEN} ✅ Git hooks are configured${NC}"
fi

# Run initial quality checks
echo -e "${CYAN} 🔍 Running initial quality checks...${NC}"
if composer run-script --list | grep -q "quality"; then
    if composer quality; then
        echo -e "${GREEN} ✅ Quality checks passed${NC}"
    else
        echo -e "${YELLOW} ⚠️  Quality checks failed. This is normal for initial setup.${NC}"
    fi
else
    echo -e "${GRAY} ⚠️  Quality checks not configured yet.${NC}"
fi

# Run tests to verify setup
echo -e "${CYAN} 🧪 Running tests to verify setup...${NC}"
if composer test:unit; then
    echo -e "${GREEN} ✅ Unit tests passed${NC}"
else
    echo -e "${YELLOW} ⚠️  Some tests failed. This may be normal for initial setup.${NC}"
fi

echo -e "\n${GREEN} 🎉 Development environment setup completed!${NC}"
echo -e "${CYAN} 📋 Available commands:${NC}"
echo -e "${GRAY}   composer test          - Run all tests${NC}"
echo -e "${GRAY}   composer test:unit     - Run unit tests${NC}"
echo -e "${GRAY}   composer test:integration - Run integration tests${NC}"
echo -e "${GRAY}   composer quality       - Run quality checks${NC}"
echo -e "${GRAY}   composer lint          - Check code style${NC}"
echo -e "${GRAY}   composer lint:fix      - Fix code style issues${NC}"
echo -e "${GRAY}   composer analyze       - Run static analysis${NC}"
echo -e "${GRAY}   ./scripts/release.sh   - Create a new release${NC}"
echo -e "\n${CYAN} 💡 Next steps:${NC}"
echo -e "${GRAY}   1. Review the semantic-versioning.md guide${NC}"
echo -e "${GRAY}   2. Set up your IDE with PHPStan and PHPCS${NC}"
echo -e "${GRAY}   3. Configure your git hooks${NC}"
echo -e "${GRAY}   4. Start developing with conventional commits${NC}"
