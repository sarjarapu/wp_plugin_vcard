# Competitor Page Download Scripts

This directory contains reusable scripts for downloading competitor pages from sitemaps.

## Overview

The workflow consists of two main steps:
1. **Extract URLs** from sitemap files based on patterns
2. **Download HTML files** from those URLs

## Scripts

### 1. `extract_urls_from_sitemaps.py`

Extracts URLs from sitemap files based on page type or custom patterns.

**Usage:**
```bash
python3 extract_urls_from_sitemaps.py <page_type> <output_tsv> [custom_pattern]
```

**Supported Page Types:**
- `services_listing` - Service listing pages (`/services`, `/dental-services`)
- `team` - Team/staff pages (`/team`, `/staff`, `/doctors`)
- `doctor_profiles` - Individual doctor profile pages (`/dr-*`, `/doctor-*`)
- `about` - About pages (`/about`, `/about-us`)
- `contact` - Contact pages (`/contact`, `/contact-us`)
- `custom` - Use with custom pattern

**Examples:**
```bash
# Extract service listing URLs
python3 extract_urls_from_sitemaps.py services_listing services_listing_urls.tsv

# Extract team pages with custom pattern
python3 extract_urls_from_sitemaps.py custom team_urls.tsv '/team|/staff|/meet-our-team'
```

**Output:**
Creates a TSV file with format: `rank<TAB>practice_name<TAB>url`

### 2. `download_pages.sh`

Downloads HTML files from URLs listed in a TSV file.

**Usage:**
```bash
./download_pages.sh <sites_list.tsv> <output_dir> [page_label]
```

**TSV Format:**
```
rank<TAB>site_name<TAB>url
```

**Examples:**
```bash
# Download service listing pages
./download_pages.sh services_listing_urls.tsv docs/websites/pages/services/listing services_listing

# Download team pages
./download_pages.sh team_urls.tsv docs/websites/pages/team team
```

**Output:**
Creates HTML files named: `{rank}_{slugified_name}_{page_label}.html`

### 3. `download_competitor_pages.sh` (Recommended)

Combines URL extraction and downloading into one workflow.

**Usage:**
```bash
./download_competitor_pages.sh <page_type> <output_dir> [page_label] [custom_pattern]
```

**Examples:**
```bash
# Download service listing pages
./download_competitor_pages.sh services_listing docs/websites/pages/services/listing services_listing

# Download team pages
./download_competitor_pages.sh team docs/websites/pages/team team

# Download with custom pattern
./download_competitor_pages.sh custom docs/websites/pages/custom custom_page '/custom-pattern/'
```

## Complete Workflow Examples

### Download Service Listing Pages

```bash
# Option 1: Use the combined script (recommended)
./download_competitor_pages.sh services_listing \
  docs/websites/pages/services/listing \
  services_listing

# Option 2: Step by step
python3 extract_urls_from_sitemaps.py services_listing services_listing_urls.tsv
./download_pages.sh services_listing_urls.tsv docs/websites/pages/services/listing services_listing
```

### Download Team Pages

```bash
./download_competitor_pages.sh team \
  docs/websites/pages/team \
  team
```

### Download Doctor Profile Pages

```bash
./download_competitor_pages.sh doctor_profiles \
  docs/websites/pages/doctors \
  doctor_profile
```

### Download Custom Page Type

```bash
# Example: Download FAQ pages
./download_competitor_pages.sh custom \
  docs/websites/pages/faq \
  faq \
  '/faq|/frequently-asked-questions'
```

## File Structure

After running the scripts, you'll have:

```
docs/websites/pages/
├── services/
│   └── listing/
│       ├── services_listing_urls.tsv
│       ├── 07_mosaic_dentistry_services_listing.html
│       ├── 14_swish_smiles_cedar_park_services_listing.html
│       └── ...
├── team/
│   ├── team_urls.tsv
│   ├── 07_mosaic_dentistry_team.html
│   └── ...
└── ...
```

## Notes

- **Aviva Dental Care (rank 16)** is automatically skipped for `services_listing` since they don't have a services listing page
- All scripts handle both formatted and single-line XML sitemap formats
- URLs are extracted using regex patterns that match common URL structures
- Downloaded files are named with rank, slugified practice name, and page label

## Adding New Page Types

To add a new page type, edit `extract_urls_from_sitemaps.py` and add to the `DEFAULT_PATTERNS` dictionary:

```python
DEFAULT_PATTERNS = {
    'services_listing': r'/(services|dental-services)/?$|/dental-services-cedar-park',
    'team': r'/(team|staff|doctors|meet-our-team|our-team)/?$',
    # Add your new pattern here
    'new_page_type': r'/your-pattern-here',
}
```

## Troubleshooting

**No URLs found:**
- Check that the sitemap files exist in `docs/websites/pages/sitemap/`
- Verify the pattern matches the URL structure in the sitemaps
- Try using a custom pattern with the `custom` page type

**Download failures:**
- Some websites may block automated downloads
- Check the URLs manually to ensure they're accessible
- The script will continue with other URLs if one fails

