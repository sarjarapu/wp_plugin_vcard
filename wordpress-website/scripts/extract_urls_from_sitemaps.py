#!/usr/bin/env python3
"""
Generic script to extract URLs from sitemap files based on URL patterns.
Can be used for services listing, team pages, doctor profiles, etc.

Usage:
    python3 extract_urls_from_sitemaps.py <page_type> <output_tsv> [pattern_regex]

Examples:
    # Extract service listing pages
    python3 extract_urls_from_sitemaps.py services_listing services_listing_urls.tsv
    
    # Extract team pages
    python3 extract_urls_from_sitemaps.py team team_urls.tsv '/team|/staff|/doctors'
    
    # Extract doctor profile pages
    python3 extract_urls_from_sitemaps.py doctor_profiles doctor_profiles_urls.tsv '/dr-|/doctor-'
"""

import sys
import re
from pathlib import Path

# Default patterns for common page types
DEFAULT_PATTERNS = {
    'services_listing': r'/(services|dental-services)/?$|/dental-services-cedar-park',
    'team': r'/(team|staff|doctors|meet-our-team|our-team)/?$',
    'doctor_profiles': r'/(dr-|doctor-|dentist-)[^/]+/?$',
    'about': r'/(about|about-us)/?$',
    'contact': r'/(contact|contact-us)/?$',
}

def is_matching_url(url, pattern, exclude_patterns=None):
    """
    Check if URL matches the pattern and doesn't match exclude patterns.
    
    Args:
        url: URL to check
        pattern: Regex pattern to match
        exclude_patterns: List of patterns to exclude (e.g., individual service pages)
    """
    url_lower = url.lower()
    
    # Check if URL matches the pattern
    if not re.search(pattern, url_lower, re.I):
        return False
    
    # Apply exclude patterns if provided
    if exclude_patterns:
        for exclude_pattern in exclude_patterns:
            if re.search(exclude_pattern, url_lower, re.I):
                return False
    
    return True

def extract_urls_from_sitemaps(page_type, output_file, custom_pattern=None, exclude_patterns=None):
    """
    Extract URLs from sitemap files based on page type.
    
    Args:
        page_type: Type of page (e.g., 'services_listing', 'team')
        output_file: Path to output TSV file
        custom_pattern: Custom regex pattern (overrides default)
        exclude_patterns: List of patterns to exclude
    """
    sitemap_dir = Path('docs/websites/pages/sitemap')
    competitors_file = Path('docs/strategy/competitor/top_competitors_home.tsv')
    output_path = Path(output_file)
    
    # Get pattern
    if custom_pattern:
        pattern = custom_pattern
    elif page_type in DEFAULT_PATTERNS:
        pattern = DEFAULT_PATTERNS[page_type]
    else:
        print(f"Error: Unknown page type '{page_type}' and no custom pattern provided.")
        print(f"Available types: {', '.join(DEFAULT_PATTERNS.keys())}")
        sys.exit(1)
    
    # Default exclude patterns for services (to avoid individual service pages)
    if page_type == 'services_listing' and not exclude_patterns:
        exclude_patterns = [r'/(services|service)/[^/]+/']
    
    # Read competitors list
    competitors = {}
    with open(competitors_file, 'r') as f:
        for line in f:
            parts = line.strip().split('\t')
            if len(parts) >= 3:
                rank = parts[0]
                name = parts[1]
                competitors[rank] = name
    
    results = []
    
    # Process each sitemap file
    for sitemap_file in sorted(sitemap_dir.glob('*_sitemap.xml')):
        rank_match = re.match(r'(\d+)_', sitemap_file.name)
        if not rank_match:
            continue
        rank = rank_match.group(1)
        
        if rank not in competitors:
            continue
        
        name = competitors[rank]
        
        # Skip Aviva (rank 16) for services listing (they don't have one)
        if page_type == 'services_listing' and rank == '16':
            continue
        
        try:
            # Read content and extract URLs using regex
            content = sitemap_file.read_text(encoding='utf-8')
            urls = re.findall(r'<loc>(https?://[^<]+)</loc>', content)
            
            # Find matching URLs
            matching_urls = []
            for url in urls:
                if is_matching_url(url, pattern, exclude_patterns):
                    matching_urls.append(url)
            
            # For services listing, prefer /services over /dental-services
            if page_type == 'services_listing' and matching_urls:
                preferred_url = None
                for url in matching_urls:
                    if '/services' in url.lower() and '/dental-services' not in url.lower():
                        preferred_url = url
                        break
                if preferred_url:
                    matching_urls = [preferred_url]
                else:
                    matching_urls = [matching_urls[0]]
            
            if matching_urls:
                # Take the first matching URL
                results.append((rank, name, matching_urls[0]))
                print(f'{rank}: {name} -> {matching_urls[0]}')
        except Exception as e:
            print(f'Error processing {sitemap_file.name}: {e}', file=sys.stderr)
    
    # Write TSV file
    output_path.parent.mkdir(parents=True, exist_ok=True)
    with open(output_path, 'w') as f:
        for rank, name, url in results:
            f.write(f'{rank}\t{name}\t{url}\n')
    
    print(f'\nTSV file created: {output_path}')
    print(f'Total entries: {len(results)}')
    return len(results)

if __name__ == '__main__':
    if len(sys.argv) < 3:
        print(__doc__)
        sys.exit(1)
    
    page_type = sys.argv[1]
    output_file = sys.argv[2]
    custom_pattern = sys.argv[3] if len(sys.argv) > 3 else None
    
    extract_urls_from_sitemaps(page_type, output_file, custom_pattern)

