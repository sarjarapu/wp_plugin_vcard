#!/bin/bash

# Pre-PR Check Script
# Runs all checks that CI runs before submitting a PR
# Usage: ./scripts/pre-pr-check.sh
# Or: composer pr-check

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${YELLOW}========================================${NC}"
echo -e "${YELLOW}Running Pre-PR Checks${NC}"
echo -e "${YELLOW}========================================${NC}\n"

# Change to plugin directory (in case script is run from project root)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$PLUGIN_DIR"

ERRORS=0

# 1. Security Audit (composer audit)
echo -e "\n${YELLOW}[1/4] Running security audit...${NC}"
AUDIT_OUTPUT=$(composer audit --no-interaction 2>&1)
AUDIT_EXIT=$?

if [ $AUDIT_EXIT -eq 0 ]; then
    echo -e "${GREEN}✓ Security audit passed${NC}"
elif echo "$AUDIT_OUTPUT" | grep -q "security vulnerability advisories found"; then
    echo -e "${RED}✗ Security vulnerabilities found!${NC}"
    echo "$AUDIT_OUTPUT"
    ERRORS=$((ERRORS + 1))
else
    # Exit code 2 usually means abandoned packages (warnings, not errors)
    if echo "$AUDIT_OUTPUT" | grep -q "abandoned package"; then
        echo -e "${YELLOW}⚠ Security audit: Abandoned packages detected (warnings)${NC}"
        echo "$AUDIT_OUTPUT" | grep -A 5 "abandoned package" || echo "$AUDIT_OUTPUT"
        echo -e "${YELLOW}Note: These are transitive dependencies. Review if acceptable.${NC}"
    else
        echo -e "${RED}✗ Security audit failed${NC}"
        echo "$AUDIT_OUTPUT"
        ERRORS=$((ERRORS + 1))
    fi
fi

# 2. Static Analysis (PHPStan)
echo -e "\n${YELLOW}[2/4] Running static analysis (PHPStan)...${NC}"
if composer analyze; then
    echo -e "${GREEN}✓ Static analysis passed${NC}"
else
    echo -e "${RED}✗ Static analysis failed${NC}"
    ERRORS=$((ERRORS + 1))
fi

# 3. Code Style (PHPCS)
echo -e "\n${YELLOW}[3/4] Running code style check (PHPCS)...${NC}"
if composer lint; then
    echo -e "${GREEN}✓ Code style check passed${NC}"
else
    echo -e "${RED}✗ Code style check failed${NC}"
    ERRORS=$((ERRORS + 1))
fi

# 4. Unit Tests
echo -e "\n${YELLOW}[4/4] Running unit tests...${NC}"
if composer test; then
    echo -e "${GREEN}✓ Unit tests passed${NC}"
else
    echo -e "${RED}✗ Unit tests failed${NC}"
    ERRORS=$((ERRORS + 1))
fi

# Summary
echo -e "\n${YELLOW}========================================${NC}"
if [ $ERRORS -eq 0 ]; then
    echo -e "${GREEN}All checks passed! ✓${NC}"
    echo -e "${GREEN}Ready to submit PR${NC}"
    echo -e "${YELLOW}========================================${NC}\n"
    exit 0
else
    echo -e "${RED}$ERRORS check(s) failed ✗${NC}"
    echo -e "${RED}Please fix the issues above before submitting PR${NC}"
    echo -e "${YELLOW}========================================${NC}\n"
    exit 1
fi

