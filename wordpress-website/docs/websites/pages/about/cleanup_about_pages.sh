#!/bin/bash

# Script to keep only main "About the Practice" pages
# Remove doctor/staff/team/location-specific pages

ABOUT_DIR="docs/websites/pages/about"

cd "$ABOUT_DIR" || exit 1

# Patterns for files to REMOVE (doctor/staff/team/location pages)
REMOVE_PATTERNS=(
    "*doctor*"
    "*staff*"
    "*meet-dr*"
    "*meet-the-doctor*"
    "*meet-the-team*"
    "*meet-our-team*"
    "*our-team*"
    "*team*.html"
    "*dentist-near*"
    "*dentist-in*"
    "*dentist-office*"
    "*dentist-leander*"
    "*dentist-cedar*"
    "*dentista-en*"
)

# Patterns for sub-pages to REMOVE (not main about pages)
SUB_PAGE_PATTERNS=(
    "*_about_*"  # Sub-pages like about_meet-the-doctor, about_office-tour, etc.
    "*_our-practice_*"  # Sub-pages like our-practice_meet-the-team, etc.
    "*_about-us_*"  # Sub-pages like about-us_meet-our-dentist, etc.
)

# Keep only main pages (standalone about.html, about-us.html, our-practice.html)
# But also keep some practice info pages that might be standalone

echo "=== Identifying files to remove ==="
echo ""

# Find files matching remove patterns
files_to_remove=()
for pattern in "${REMOVE_PATTERNS[@]}"; do
    while IFS= read -r file; do
        # Skip script files
        [[ "$file" == *.sh ]] && continue
        if [ -f "$file" ]; then
            files_to_remove+=("$file")
        fi
    done < <(ls -1 $pattern 2>/dev/null)
done

# Find sub-pages
for pattern in "${SUB_PAGE_PATTERNS[@]}"; do
    while IFS= read -r file; do
        # Skip script files
        [[ "$file" == *.sh ]] && continue
        if [ -f "$file" ]; then
            files_to_remove+=("$file")
        fi
    done < <(ls -1 $pattern 2>/dev/null)
done

# Remove duplicates
IFS=$'\n' files_to_remove=($(printf '%s\n' "${files_to_remove[@]}" | sort -u))

echo "Files to remove: ${#files_to_remove[@]}"
echo ""

# Show what will be removed
for file in "${files_to_remove[@]}"; do
    echo "  REMOVE: $file"
done

echo ""
echo "=== Files to KEEP (main About pages) ==="
echo ""

# Find files to keep (main about pages - standalone, not sub-pages)
files_to_keep=()
for file in *.html; do
    # Skip script files
    [[ "$file" == *.sh ]] && continue
    
    # Skip if it's in the remove list
    skip=false
    for remove_file in "${files_to_remove[@]}"; do
        if [ "$file" == "$remove_file" ]; then
            skip=true
            break
        fi
    done
    
    if [ "$skip" == false ]; then
        # Check if it's a main about page (ends with about.html, about-us.html, our-practice.html, or practice.html)
        # But not a sub-page (doesn't contain _about_, _our-practice_, _about-us_)
        if [[ "$file" =~ _(about|about-us|our-practice|practice)\.html$ ]] && [[ ! "$file" =~ _about_|_our-practice_|_about-us_ ]]; then
            files_to_keep+=("$file")
            echo "  KEEP: $file"
        # Also check for other standalone pages that might be about the practice
        elif [[ "$file" =~ ^[0-9]+_.*_(about|about-us|our-practice|practice)\.html$ ]] && [[ ! "$file" =~ _about_|_our-practice_|_about-us_ ]]; then
            files_to_keep+=("$file")
            echo "  KEEP: $file"
        fi
    fi
done

echo ""
echo "Total to remove: ${#files_to_remove[@]}"
echo "Total to keep: ${#files_to_keep[@]}"
echo ""

# Remove the files
for file in "${files_to_remove[@]}"; do
    rm -f "$file"
    echo "Removed: $file"
done

echo ""
echo "Cleanup complete!"
echo "Remaining files: $(ls -1 *.html 2>/dev/null | wc -l)"

