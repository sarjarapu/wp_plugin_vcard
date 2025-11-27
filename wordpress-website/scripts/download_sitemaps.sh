#!/bin/bash

# Script to download sitemaps from competitor websites
TSV_FILE="docs/strategy/competitor/top_competitors_home.tsv"
SITEMAP_DIR="docs/strategy/competitor/sitemap"

# Common sitemap paths to check
SITEMAP_PATHS=(
    "/sitemap.xml"
    "/sitemap_index.xml"
    "/wp-sitemap.xml"
    "/sitemap1.xml"
    "/sitemaps.xml"
)

# Function to sanitize practice name for filename
sanitize_name() {
    echo "$1" | tr '[:upper:]' '[:lower:]' | sed 's/[^a-z0-9]/_/g' | sed 's/__*/_/g' | sed 's/^_\|_$//g'
}

# Read TSV file and process each line
while IFS=$'\t' read -r rank name url; do
    # Skip empty lines
    [[ -z "$rank" || -z "$name" || -z "$url" ]] && continue
    
    # Remove trailing slash from URL
    url="${url%/}"
    
    # Sanitize practice name
    sanitized_name=$(sanitize_name "$name")
    
    # Create filename
    filename="${rank}_${sanitized_name}_sitemap.xml"
    filepath="${SITEMAP_DIR}/${filename}"
    
    echo "Processing: $rank - $name"
    echo "  URL: $url"
    
    # Try each sitemap path
    downloaded=false
    for sitemap_path in "${SITEMAP_PATHS[@]}"; do
        sitemap_url="${url}${sitemap_path}"
        
        # Check if sitemap exists (HTTP 200)
        if curl -s -o /dev/null -w "%{http_code}" --max-time 10 "$sitemap_url" | grep -q "200"; then
            echo "  Found sitemap at: $sitemap_url"
            curl -s --max-time 30 "$sitemap_url" -o "$filepath"
            
            # Verify it's actually XML
            if head -n 1 "$filepath" | grep -q "<?xml"; then
                echo "  ✓ Downloaded: $filename"
                downloaded=true
                break
            else
                rm -f "$filepath"
            fi
        fi
    done
    
    if [ "$downloaded" = false ]; then
        echo "  ✗ No sitemap found"
    fi
    
    echo ""
    
done < "$TSV_FILE"

echo "Done processing all competitors."

