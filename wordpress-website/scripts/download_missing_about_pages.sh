#!/bin/bash

# Script to download missing About pages and alternative URLs
# Get the directory where the script is located
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ABOUT_DIR="$SCRIPT_DIR"

cd "$ABOUT_DIR" || exit 1

# Function to sanitize practice name for filename
sanitize_name() {
    echo "$1" | tr '[:upper:]' '[:lower:]' | sed 's/[^a-z0-9]/_/g' | sed 's/__*/_/g' | sed 's/^_\|_$//g'
}

# Array of rank, practice name, and URL
declare -a practices=(
    "01|Buttercup Dental|https://www.buttercupdental.com/new-patients"
    "02|New Hope Dentist|https://www.newhopedentist.com/"
    "03|Bowcutt Dental|https://www.bowcuttdental.com/dentist-cedar-park-tx/"
    "04|Family Dental of Cedar Park|https://familydentalofcedarpark.com/"
    "06|My Cedar Park Dentist|https://www.mycedarparkdentist.com/our-dentist-office/"
    "13|Prime Dental of Cedar Park|https://primedentalofcp.com/"
    "18|Cedar Park Modern Dentistry & Orthodontics|https://www.cedarparkmoderndentistry.com/about-us/"
    "20|Morgan Dental|https://www.morgandental.net/about/"
    "21|Perch Dentistry|https://www.perchdentistry.com/dentistry"
    "23|Whitestone Family Dentistry|https://whitestonefamilydentistry.com/"
)

echo "=== Downloading Missing About Pages ==="
echo ""

for practice_info in "${practices[@]}"; do
    IFS='|' read -r rank name url <<< "$practice_info"
    
    # Sanitize practice name
    sanitized_name=$(sanitize_name "$name")
    
    # Create filename
    filename="${rank}_${sanitized_name}_about.html"
    filepath="${ABOUT_DIR}/${filename}"
    
    echo "Processing: $rank - $name"
    echo "  URL: $url"
    echo "  Saving as: $filename"
    
    # Download the page
    curl -s --max-time 30 -L "$url" -o "$filepath" 2>/dev/null
    
    # Verify it's HTML (check if file exists and has content, and contains HTML tags)
    if [ -f "$filepath" ] && [ -s "$filepath" ]; then
        if head -n 10 "$filepath" | grep -qi "<html\|<!DOCTYPE\|<body\|<head"; then
            echo "  ✓ Downloaded successfully ($(wc -c < "$filepath" | tr -d ' ') bytes)"
        else
            echo "  ⚠ Downloaded but may not be valid HTML"
        fi
    else
        echo "  ✗ Failed to download"
        rm -f "$filepath"
    fi
    
    echo ""
done

echo "Done downloading all pages."
echo "Total files: $(ls -1 *.html 2>/dev/null | wc -l)"

