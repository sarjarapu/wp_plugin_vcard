#!/usr/bin/env python3
#
# Create Linear.app tickets for all Aviva service pages
# Usage: ./create_linear_service_tickets.py

import os
import json
import subprocess
import time
import re

# Get API key from ~/.zshrc
def get_api_key():
    zshrc_path = os.path.expanduser("~/.zshrc")
    try:
        with open(zshrc_path, 'r') as f:
            for line in f:
                if line.startswith("export LINEAR_API_KEY="):
                    # Extract the value, removing quotes
                    match = re.search(r'LINEAR_API_KEY="([^"]+)"', line)
                    if match:
                        return match.group(1)
                    # Try without quotes
                    match = re.search(r'LINEAR_API_KEY=([^\s]+)', line)
                    if match:
                        return match.group(1).strip('"\'')
    except FileNotFoundError:
        pass
    return None

API_KEY = get_api_key()
if not API_KEY:
    print("Error: LINEAR_API_KEY not found in ~/.zshrc", file=os.sys.stderr)
    os.sys.exit(1)

# Team ID for Minisites team
TEAM_ID = "5b5c2471-d25c-4b70-81bf-5b2707f6553f"

# Parent issue identifier (MIN-42)
PARENT_ISSUE_NUMBER = 42

def get_parent_issue_id():
    """Look up MIN-42 to get its internal ID"""
    query = {
        "query": """
        query GetIssue($teamId: String!, $number: Float!) {
            team(id: $teamId) {
                issues(filter: { number: { eq: $number } }, first: 1) {
                    nodes {
                        id
                        identifier
                        title
                    }
                }
            }
        }
        """,
        "variables": {
            "teamId": TEAM_ID,
            "number": PARENT_ISSUE_NUMBER
        }
    }
    
    import urllib.request
    import urllib.parse
    
    data = json.dumps(query).encode('utf-8')
    req = urllib.request.Request(
        'https://api.linear.app/graphql',
        data=data,
        headers={
            'Authorization': API_KEY,
            'Content-Type': 'application/json'
        }
    )
    
    try:
        with urllib.request.urlopen(req) as response:
            result = json.loads(response.read().decode('utf-8'))
            
            if 'errors' in result:
                print(f"❌ Error looking up parent issue MIN-{PARENT_ISSUE_NUMBER}")
                print(f"   Error: {result['errors']}")
                return None
            
            nodes = result.get('data', {}).get('team', {}).get('issues', {}).get('nodes', [])
            if nodes:
                issue = nodes[0]
                print(f"✅ Found parent issue: {issue['identifier']} - {issue['title']}")
                return issue['id']
            else:
                print(f"❌ Parent issue MIN-{PARENT_ISSUE_NUMBER} not found")
                return None
    except Exception as e:
        print(f"❌ Error looking up parent issue: {e}")
        return None

# List of all services
services = [
    "dental-exams-cleanings",
    "fillings",
    "root-canal",
    "tooth-extractions",
    "childrens-dentistry",
    "preventive-care",
    "teeth-whitening",
    "veneers",
    "invisalign",
    "dental-bonding",
    "smile-makeovers",
    "dental-crowns",
    "dental-bridges",
    "dental-implants",
    "dentures",
    "emergency-dentistry",
    "tooth-pain-relief",
    "broken-tooth-repair",
    "sedation-dentistry",
    "periodontal-treatment",
    "night-guards",
    "oral-cancer-screening",
    "digital-x-rays",
    "sleep-apnea-treatment",
]

def create_ticket(service_name, parent_id=None):
    title = f"aviva: create content for {service_name}"
    
    description = f"""## Summary
Create service page content for {service_name} following the comprehensive workflow guide.

## Instructions

**CRITICAL: Follow ALL instructions in:**

`wordpress-website/docs/websites/pages/services/create_service_page.md`

### Service Name
Use this exact service name throughout: **`{service_name}`**

### Key Workflow Steps

1. **Git Workflow (CRITICAL FIRST STEP)**:
   - Create branch: `feature/{service_name}` from `aviva-website` (NOT `main`)
   - PR must target: `aviva-website` (NOT `main`)

2. **Phase 1-3**: Service URL discovery, TSV creation, HTML download
   - TSV file: `wordpress-website/docs/websites/pages/services/{service_name}/{service_name}_urls.tsv`
   - Download command: `./scripts/download_service.sh wordpress-website/docs/websites/pages/services/{service_name}/{service_name}_urls.tsv {service_name}`

3. **Phase 4-5**: Deep content analysis and strategy creation
   - Strategy file: `wordpress-website/docs/websites/pages/services/{service_name}/strategy.md`

4. **Phase 6**: Create final HTML content
   - Output file: `wordpress-website/docs/websites/new-content/services/{service_name}.html`
   - Match styling from: `wordpress-website/docs/websites/new-content/home.html` and `wordpress-website/docs/websites/new-content/about.html`

5. **Phase 7**: Lead conversion optimization

### Critical Rules

- **NEVER include pricing information** - always redirect to consultation
- **Include location** (Cedar Park, TX) in title, H1, and throughout content
- **Match existing style** from home.html/about.html
- **All CTAs** must lead to consultation/contact

### Success Criteria

- [ ] Branch created: `feature/{service_name}` from `aviva-website`
- [ ] 12+ competitor pages analyzed
- [ ] Comprehensive strategy.md created
- [ ] Final HTML matches style and converts
- [ ] No pricing information included
- [ ] All CTAs lead to consultation
- [ ] PR created targeting `aviva-website`
"""
    
    # Build input object
    input_data = {
        "teamId": TEAM_ID,
        "title": title,
        "description": description,
        "priority": 3
    }
    
    # Add parentId if provided
    if parent_id:
        input_data["parentId"] = parent_id
    
    # Build GraphQL mutation
    mutation = {
        "query": """
        mutation CreateIssue($input: IssueCreateInput!) {
            issueCreate(input: $input) {
                success
                issue {
                    id
                    identifier
                    title
                    url
                }
            }
        }
        """,
        "variables": {
            "input": input_data
        }
    }
    
    print(f"Creating ticket for: {service_name}...")
    
    # Make API request
    import urllib.request
    import urllib.parse
    
    data = json.dumps(mutation).encode('utf-8')
    req = urllib.request.Request(
        'https://api.linear.app/graphql',
        data=data,
        headers={
            'Authorization': API_KEY,
            'Content-Type': 'application/json'
        }
    )
    
    try:
        with urllib.request.urlopen(req) as response:
            result = json.loads(response.read().decode('utf-8'))
            
            if 'errors' in result:
                print(f"❌ Failed to create ticket for: {service_name}")
                print(f"   Error: {result['errors']}")
                return False
            
            if result.get('data', {}).get('issueCreate', {}).get('success'):
                issue = result['data']['issueCreate']['issue']
                identifier = issue['identifier']
                url = issue['url']
                print(f"✅ Created: {identifier} - {title}")
                print(f"   URL: {url}")
                return True
            else:
                print(f"❌ Failed to create ticket for: {service_name}")
                print(f"   Response: {result}")
                return False
    except Exception as e:
        print(f"❌ Error creating ticket for: {service_name}")
        print(f"   Exception: {e}")
        return False

# Get parent issue ID first
print("Looking up parent issue MIN-42...")
parent_id = get_parent_issue_id()

if not parent_id:
    print("\n⚠️  Warning: Could not find parent issue MIN-42")
    print("   Continuing without parent relationship...")
    print("   You can manually link tickets later if needed.\n")
    use_parent = False
else:
    use_parent = True
    print()

# Create tickets for all services
success_count = 0
for service in services:
    if create_ticket(service, parent_id if use_parent else None):
        success_count += 1
    time.sleep(1)  # Small delay to avoid rate limiting

print(f"\nDone! Created {success_count} out of {len(services)} tickets.")
if use_parent:
    print(f"All tickets are sub-issues of MIN-{PARENT_ISSUE_NUMBER}")

