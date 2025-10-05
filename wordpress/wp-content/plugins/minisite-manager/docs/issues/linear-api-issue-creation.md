# Linear API Issue Creation Guide

## Overview

This document provides the process and commands for creating Linear issues via the GraphQL API, specifically for tracking development work on the Minisite Manager WordPress plugin.

## Prerequisites

1. **Linear API Key**: Store your Linear API key in `~/.zshrc`:
   ```bash
   export LINEAR_API_KEY="lin_api_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
   ```

2. **Team ID**: The Minisites team ID is: `5b5c2471-d25c-4b70-81bf-5b2707f6553f`

## Quick Commands

### Get Team ID
```bash
API_KEY=$(grep "LINEAR_API_KEY" ~/.zshrc | cut -d'=' -f2 | tr -d '"') && \
curl -X POST \
  -H "Authorization: $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"query": "query { teams { nodes { id name } } }"}' \
  https://api.linear.app/graphql
```

### Create Feature Issue
```bash
API_KEY=$(grep "LINEAR_API_KEY" ~/.zshrc | cut -d'=' -f2 | tr -d '"') && \
curl -X POST \
  -H "Authorization: $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"query": "mutation { issueCreate(input: { teamId: \"5b5c2471-d25c-4b70-81bf-5b2707f6553f\", title: \"YOUR_TITLE_HERE\", description: \"YOUR_DESCRIPTION_HERE\", priority: 3 }) { success issue { id identifier title url } } }"}' \
  https://api.linear.app/graphql
```

### Create Bug Issue
```bash
API_KEY=$(grep "LINEAR_API_KEY" ~/.zshrc | cut -d'=' -f2 | tr -d '"') && \
curl -X POST \
  -H "Authorization: $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"query": "mutation { issueCreate(input: { teamId: \"5b5c2471-d25c-4b70-81bf-5b2707f6553f\", title: \"Bug: YOUR_BUG_TITLE\", description: \"YOUR_BUG_DESCRIPTION\", priority: 2 }) { success issue { id identifier title url } } }"}' \
  https://api.linear.app/graphql
```

## Issue Templates

### Feature Issue Template
```bash
# Replace YOUR_TITLE and YOUR_DESCRIPTION with actual values
API_KEY=$(grep "LINEAR_API_KEY" ~/.zshrc | cut -d'=' -f2 | tr -d '"') && \
curl -X POST \
  -H "Authorization: $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"query": "mutation { issueCreate(input: { teamId: \"5b5c2471-d25c-4b70-81bf-5b2707f6553f\", title: \"Feature: YOUR_TITLE\", description: \"## Summary\nBrief description of the feature.\n\n## Completed\n- List of completed work items\n- Key patterns implemented\n- Files created/modified\n\n## Next Steps\n- Integration testing\n- Documentation updates\n- Cleanup tasks\", priority: 3 }) { success issue { id identifier title url } } }"}' \
  https://api.linear.app/graphql
```

### Bug Issue Template
```bash
# Replace YOUR_TITLE and YOUR_DESCRIPTION with actual values
API_KEY=$(grep "LINEAR_API_KEY" ~/.zshrc | cut -d'=' -f2 | tr -d '"') && \
curl -X POST \
  -H "Authorization: $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"query": "mutation { issueCreate(input: { teamId: \"5b5c2471-d25c-4b70-81bf-5b2707f6553f\", title: \"Bug: YOUR_TITLE\", description: \"## Summary\nBrief description of the bug.\n\n## Steps to Reproduce\n1. Step one\n2. Step two\n3. Step three\n\n## Expected Behavior\nWhat should happen.\n\n## Actual Behavior\nWhat actually happens.\n\n## Root Cause\nTechnical explanation of the issue.\n\n## Workaround\nTemporary solution if available.\", priority: 2 }) { success issue { id identifier title url } } }"}' \
  https://api.linear.app/graphql
```

## Priority Levels

- **Priority 1**: Critical (urgent bugs, security issues)
- **Priority 2**: High (important bugs, major features)
- **Priority 3**: Medium (normal features, minor bugs)
- **Priority 4**: Low (nice-to-have features, documentation)

## Common Use Cases

### Track Feature Refactor Work
```bash
# Example: MinisiteDisplay refactor
API_KEY=$(grep "LINEAR_API_KEY" ~/.zshrc | cut -d'=' -f2 | tr -d '"') && \
curl -X POST \
  -H "Authorization: $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"query": "mutation { issueCreate(input: { teamId: \"5b5c2471-d25c-4b70-81bf-5b2707f6553f\", title: \"Feature: Refactor MinisiteDisplay to Feature-Based Architecture\", description: \"Refactored MinisiteDisplay from single 41-line controller to feature-based architecture following Authentication patterns. Created 11 new classes with single responsibility, implemented Command/Handler pattern, added dependency injection via DisplayHooksFactory, separated business logic into MinisiteDisplayService, added comprehensive error handling and fallback rendering. Files: MinisiteDisplayFeature.php, Controllers/MinisitePageController.php, Services/MinisiteDisplayService.php, Handlers/DisplayHandler.php, Commands/DisplayMinisiteCommand.php, Hooks/DisplayHooks.php + DisplayHooksFactory.php, Http/DisplayRequestHandler.php + DisplayResponseHandler.php, Rendering/DisplayRenderer.php, WordPress/WordPressMinisiteManager.php. Next steps: Integration testing, main plugin bootstrap integration, remove old MinisitePageController.\", priority: 3 }) { success issue { id identifier title url } } }"}' \
  https://api.linear.app/graphql
```

### Track Bug Fixes
```bash
# Example: Location fields bug
API_KEY=$(grep "LINEAR_API_KEY" ~/.zshrc | cut -d'=' -f2 | tr -d '"') && \
curl -X POST \
  -H "Authorization: $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"query": "mutation { issueCreate(input: { teamId: \"5b5c2471-d25c-4b70-81bf-5b2707f6553f\", title: \"Bug: Location fields not saved when importing JSON data to new minisite draft\", description: \"## Summary\nWhen creating a new minisite draft and importing existing JSON data, the form controls are populated with the imported values (city, state, postal code), but these location fields are not saved when the user clicks save.\n\n## Steps to Reproduce\n1. Navigate to /account/sites/new/\n2. Click Create Free Draft to create a new minisite\n3. Import existing JSON data using the import functionality\n4. Observe that form fields are populated with imported data (city, state, postal code)\n5. Click Save Draft\n6. Expected: Location fields (city, state, postal code) should be saved\n7. Actual: Location fields are not saved, only default empty values persist\n\n## Root Cause\nThe issue is in the form field mapping in SitesController.php (lines 213-222). The save operation expects form fields with specific names: business_city (not contact_city), business_region (not contact_region), business_country (not contact_country), business_postal (not contact_postal). However, when importing JSON data, the form is likely populated with contact_* field names, which don'\''t match the expected business_* field names during save.\n\n## Expected Behavior\nLocation fields imported from JSON should be properly saved when the user saves the draft.\n\n## Workaround\nUsers must manually re-enter location data after importing JSON, which defeats the purpose of the import functionality.\", priority: 2 }) { success issue { id identifier title url } } }"}' \
  https://api.linear.app/graphql
```

## Troubleshooting

### Common Errors

1. **"Field IssueCreateInput.teamId of required type String! was not provided"**
   - Solution: Always include `teamId: "5b5c2471-d25c-4b70-81bf-5b2707f6553f"` in the input

2. **"Bad control character in string literal in JSON"**
   - Solution: Avoid newlines in JSON strings, use `\n` for line breaks

3. **"Field labels is not defined by type IssueCreateInput"**
   - Solution: Use `labelIds` instead of `labels` if you need to add labels

### JSON Escaping

When including special characters in descriptions:
- Use `\n` for line breaks
- Use `\"` for quotes
- Use `\\` for backslashes

## Response Format

Successful response:
```json
{
  "data": {
    "issueCreate": {
      "success": true,
      "issue": {
        "id": "73c25f54-8b51-44da-afdb-d67abc13c95d",
        "identifier": "MIN-8",
        "title": "Feature: Refactor MinisiteDisplay to Feature-Based Architecture",
        "url": "https://linear.app/minisites/issue/MIN-8/feature-refactor-minisitedisplay-to-feature-based-architecture"
      }
    }
  }
}
```

## Best Practices

1. **Use descriptive titles** that clearly indicate the type (Feature:, Bug:, etc.)
2. **Keep descriptions concise** but comprehensive
3. **Include file paths** when referencing code changes
4. **List next steps** for incomplete work
5. **Use appropriate priority levels** based on impact
6. **Test commands** before using them in production

## References

- [Linear GraphQL API Documentation](https://developers.linear.app/docs/graphql/working-with-the-graphql-api)
- [Linear API Key Setup](https://linear.app/settings/account/security)
