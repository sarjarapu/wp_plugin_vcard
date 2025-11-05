#!/bin/bash

# Pre-PR Check Script
# Runs all checks that CI runs before submitting a PR
# Usage: ./scripts/pre-pr-check.sh
# Or: composer pr-check

# Note: We don't use 'set -e' because we want to continue and report all errors
# We handle errors manually for each step

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
echo -e "${YELLOW}Auto-fixing code style issues...${NC}"
LINT_FIX_OUTPUT=$(composer lint:fix 2>&1)
LINT_FIX_EXIT=$?

if [ $LINT_FIX_EXIT -eq 0 ]; then
    echo -e "${GREEN}✓ Auto-fix completed${NC}"
else
    echo -e "${YELLOW}⚠ Auto-fix completed with warnings${NC}"
fi

echo -e "${YELLOW}Running code style check...${NC}"
LINT_OUTPUT=$(composer lint 2>&1)
LINT_EXIT=$?

if [ $LINT_EXIT -eq 0 ]; then
    echo -e "${GREEN}✓ Code style check passed${NC}"
else
    echo -e "${RED}✗ Code style check failed${NC}"
    echo "$LINT_OUTPUT"
    ERRORS=$((ERRORS + 1))
    echo -e "${YELLOW}Tip: Some issues may require manual fixes${NC}"
fi

# 4. Unit Tests
echo -e "\n${YELLOW}[4/6] Running unit tests...${NC}"
if composer test; then
    echo -e "${GREEN}✓ Unit tests passed${NC}"
else
    echo -e "${RED}✗ Unit tests failed${NC}"
    ERRORS=$((ERRORS + 1))
fi

# 5. Unit Tests Coverage Report
echo -e "\n${YELLOW}[5/6] Running unit tests with coverage...${NC}"
COVERAGE_DIR="coverage/unit"
mkdir -p "$COVERAGE_DIR"
COVERAGE_HTML_DIR="coverage/unit/html"
mkdir -p "$COVERAGE_HTML_DIR"

# Generate both text and HTML reports
COVERAGE_OUTPUT=$(./vendor/bin/phpunit --testsuite=Unit --coverage-text --coverage-html="$COVERAGE_HTML_DIR" --coverage-filter=src/ 2>&1)
COVERAGE_EXIT=$?
echo "$COVERAGE_OUTPUT" > "$COVERAGE_DIR/coverage.txt"

if [ $COVERAGE_EXIT -eq 0 ]; then
    echo -e "${GREEN}✓ Unit tests coverage generated${NC}"
    echo -e "${YELLOW}Coverage reports saved to:${NC}"
    echo -e "  - Text: $COVERAGE_DIR/coverage.txt"
    if [ -f "$COVERAGE_HTML_DIR/index.html" ]; then
        echo -e "  - HTML: $COVERAGE_HTML_DIR/index.html"
    else
        echo -e "  - HTML: ${YELLOW}(not generated - no coverage data)${NC}"
    fi
    # Show summary from coverage report if available
    if echo "$COVERAGE_OUTPUT" | grep -q "Summary:"; then
        echo -e "\n${YELLOW}Unit Tests Coverage Summary:${NC}"
        echo "$COVERAGE_OUTPUT" | grep -A 3 "Summary:" | head -4
    else
        echo -e "${YELLOW}Note: Unit tests primarily cover test code, not src/ directory${NC}"
    fi
elif echo "$COVERAGE_OUTPUT" | grep -q "No coverage driver available"; then
    echo -e "${YELLOW}⚠ Coverage driver not available (PCOV/Xdebug not installed)${NC}"
    echo -e "${YELLOW}Skipping coverage report...${NC}"
else
    echo -e "${RED}✗ Unit tests coverage failed${NC}"
    echo "$COVERAGE_OUTPUT" | tail -20
    ERRORS=$((ERRORS + 1))
fi

# 6. Integration Tests Coverage Report
echo -e "\n${YELLOW}[6/6] Running integration tests with coverage...${NC}"
COVERAGE_DIR_INT="coverage/integration"
mkdir -p "$COVERAGE_DIR_INT"
COVERAGE_HTML_DIR_INT="coverage/integration/html"
mkdir -p "$COVERAGE_HTML_DIR_INT"

# Generate both text and HTML reports
COVERAGE_OUTPUT_INT=$(./vendor/bin/phpunit --testsuite=Integration --coverage-text --coverage-html="$COVERAGE_HTML_DIR_INT" --coverage-filter=src/ 2>&1)
COVERAGE_EXIT_INT=$?
echo "$COVERAGE_OUTPUT_INT" > "$COVERAGE_DIR_INT/coverage.txt"

if echo "$COVERAGE_OUTPUT_INT" | grep -q "Code Coverage Report:"; then
    echo -e "${GREEN}✓ Integration tests coverage generated${NC}"
    echo -e "${YELLOW}Coverage reports saved to:${NC}"
    echo -e "  - Text: $COVERAGE_DIR_INT/coverage.txt"
    if [ -f "$COVERAGE_HTML_DIR_INT/index.html" ]; then
        echo -e "  - HTML: $COVERAGE_HTML_DIR_INT/index.html"
    else
        echo -e "  - HTML: ${YELLOW}(not generated)${NC}"
    fi
    # Show summary from coverage report
    if echo "$COVERAGE_OUTPUT_INT" | grep -q "Summary:"; then
        echo -e "\n${YELLOW}Integration Tests Coverage Summary:${NC}"
        echo "$COVERAGE_OUTPUT_INT" | grep -A 3 "Summary:" | head -4
    fi
    # Check if there were test failures (coverage still generated but tests may have failed)
    if echo "$COVERAGE_OUTPUT_INT" | grep -q "ERRORS\|FAILURES"; then
        echo -e "${YELLOW}⚠ Some integration tests failed (coverage still generated)${NC}"
        # Extract test summary
        TEST_SUMMARY=$(echo "$COVERAGE_OUTPUT_INT" | grep -E "Tests:.*Assertions:" | tail -1)
        if [ -n "$TEST_SUMMARY" ]; then
            echo -e "${YELLOW}$TEST_SUMMARY${NC}"
        fi
    fi
elif echo "$COVERAGE_OUTPUT_INT" | grep -q "No coverage driver available"; then
    echo -e "${YELLOW}⚠ Coverage driver not available (PCOV/Xdebug not installed)${NC}"
    echo -e "${YELLOW}Skipping coverage report...${NC}"
else
    echo -e "${RED}✗ Integration tests coverage failed${NC}"
    echo "$COVERAGE_OUTPUT_INT" | tail -30
    ERRORS=$((ERRORS + 1))
fi

# Summary
echo -e "\n${YELLOW}========================================${NC}"
if [ $ERRORS -eq 0 ]; then
    echo -e "${GREEN}All checks passed! ✓${NC}"
    echo -e "${GREEN}Ready to submit PR${NC}"
    echo -e "${YELLOW}Coverage reports:${NC}"
    echo -e "  - Unit (Text): $COVERAGE_DIR/coverage.txt"
    [ -f "$COVERAGE_HTML_DIR/index.html" ] && echo -e "  - Unit (HTML): $COVERAGE_HTML_DIR/index.html"
    echo -e "  - Integration (Text): $COVERAGE_DIR_INT/coverage.txt"
    [ -f "$COVERAGE_HTML_DIR_INT/index.html" ] && echo -e "  - Integration (HTML): $COVERAGE_HTML_DIR_INT/index.html"
    echo -e "${YELLOW}========================================${NC}\n"
    exit 0
else
    echo -e "${RED}$ERRORS check(s) failed ✗${NC}"
    echo -e "${RED}Please fix the issues above before submitting PR${NC}"
    echo -e "${YELLOW}Coverage reports (if generated):${NC}"
    echo -e "  - Unit (Text): $COVERAGE_DIR/coverage.txt"
    [ -f "$COVERAGE_HTML_DIR/index.html" ] && echo -e "  - Unit (HTML): $COVERAGE_HTML_DIR/index.html"
    echo -e "  - Integration (Text): $COVERAGE_DIR_INT/coverage.txt"
    [ -f "$COVERAGE_HTML_DIR_INT/index.html" ] && echo -e "  - Integration (HTML): $COVERAGE_HTML_DIR_INT/index.html"
    echo -e "${YELLOW}========================================${NC}\n"
    exit 1
fi

