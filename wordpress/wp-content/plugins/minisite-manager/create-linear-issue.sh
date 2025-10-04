#!/bin/bash

# Linear Issue Creation Script
# Usage: ./create-linear-issue.sh YOUR_API_KEY

if [ -z "$1" ]; then
    echo "Usage: $0 YOUR_LINEAR_API_KEY"
    echo "Get your API key from: https://linear.app/settings/account/security"
    exit 1
fi

API_KEY="$1"

# GraphQL mutation to create issue
MUTATION='
mutation {
  issueCreate(
    input: {
      title: "Bug: Location fields not saved when importing JSON data to new minisite draft"
      description: "## Summary
When creating a new minisite draft and importing existing JSON data, the form controls are populated with the imported values (city, state, postal code), but these location fields are not saved when the user clicks save.

## Steps to Reproduce
1. Navigate to `/account/sites/new/`
2. Click \"Create Free Draft\" to create a new minisite
3. Import existing JSON data using the import functionality
4. Observe that form fields are populated with imported data (city, state, postal code)
5. Click \"Save Draft\"
6. **Expected**: Location fields (city, state, postal code) should be saved
7. **Actual**: Location fields are not saved, only default empty values persist

## Root Cause
The issue is in the form field mapping in `SitesController.php` (lines 213-222). The save operation expects form fields with specific names:
- `business_city` (not `contact_city`)
- `business_region` (not `contact_region`) 
- `business_country` (not `contact_country`)
- `business_postal` (not `contact_postal`)

However, when importing JSON data, the form is likely populated with `contact_*` field names, which don'\''t match the expected `business_*` field names during save.

## Technical Details
- **File**: `src/Application/Controllers/Front/SitesController.php`
- **Lines**: 213-222 (Version constructor with profile fields)
- **Issue**: Form field name mismatch between import population and save operation
- **Impact**: Data loss for location information during JSON import workflow

## Expected Behavior
Location fields imported from JSON should be properly saved when the user saves the draft.

## Workaround
Users must manually re-enter location data after importing JSON, which defeats the purpose of the import functionality."
      priority: 2
      labels: { name: "bug" }
    }
  ) {
    success
    issue {
      id
      identifier
      title
      url
    }
  }
}'

# Make the API call
curl -X POST \
  -H "Authorization: $API_KEY" \
  -H "Content-Type: application/json" \
  -d "{\"query\": \"$MUTATION\"}" \
  https://api.linear.app/graphql

echo ""
echo "Issue created successfully!"
