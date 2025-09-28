#!/bin/bash

# Test script for the pre-commit hook
# This script simulates what the pre-commit hook does

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}🧪 Testing pre-commit hook functionality...${NC}"

# Test 1: Check if PHPUnit is available
echo -e "${YELLOW}Test 1: Checking PHPUnit availability...${NC}"
if [ -f "vendor/bin/phpunit" ]; then
    echo -e "${GREEN}✅ PHPUnit found${NC}"
else
    echo -e "${RED}❌ PHPUnit not found${NC}"
    exit 1
fi

# Test 2: Check if we can run PHPUnit with help
echo -e "${YELLOW}Test 2: Testing PHPUnit execution...${NC}"
if vendor/bin/phpunit --help > /dev/null 2>&1; then
    echo -e "${GREEN}✅ PHPUnit can be executed${NC}"
else
    echo -e "${RED}❌ PHPUnit execution failed${NC}"
    exit 1
fi

# Test 3: Check if we can run tests (even if they fail)
echo -e "${YELLOW}Test 3: Testing test execution...${NC}"
if vendor/bin/phpunit --testsuite=Unit --stop-on-failure > /dev/null 2>&1; then
    echo -e "${GREEN}✅ Unit tests can be executed${NC}"
else
    echo -e "${YELLOW}⚠️  Unit tests failed (this is expected if tests are broken)${NC}"
fi

# Test 4: Check coverage generation
echo -e "${YELLOW}Test 4: Testing coverage generation...${NC}"
if vendor/bin/phpunit --testsuite=Unit --coverage-text --coverage-html=build/coverage > coverage_test.tmp 2>&1; then
    echo -e "${GREEN}✅ Coverage can be generated${NC}"
    # Try to extract coverage
    COVERAGE=$(grep -E "Lines:\s+[0-9]+\.[0-9]+%" coverage_test.tmp | head -1 | grep -oE "[0-9]+\.[0-9]+%" | grep -oE "[0-9]+\.[0-9]+" || echo "")
    if [ -n "$COVERAGE" ]; then
        echo -e "${GREEN}✅ Coverage extraction works: ${COVERAGE}%${NC}"
    else
        echo -e "${YELLOW}⚠️  Could not extract coverage percentage${NC}"
    fi
else
    echo -e "${YELLOW}⚠️  Coverage generation failed (this is expected if tests are broken)${NC}"
fi

# Cleanup
rm -f coverage_test.tmp

echo -e "${GREEN}🎉 Pre-commit hook test completed!${NC}"
echo -e "${BLUE}💡 Note: The actual hook will prevent commits if tests fail or coverage is below 60%${NC}"
