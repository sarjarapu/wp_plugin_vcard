#!/bin/bash

# Script to rename files by removing URL portion from filename
# Pattern: rank_practice_name_https:__domain_page-path.html
# Result:  rank_practice_name_page-path.html

ABOUT_DIR="docs/websites/pages/about"

cd "$ABOUT_DIR" || exit 1

# Process each HTML file
for file in *.html; do
    # Skip scripts
    [[ "$file" == "download_about_pages.sh" ]] && continue
    [[ "$file" == "rename_files.sh" ]] && continue
    
    # Check if file contains the URL pattern
    if [[ "$file" =~ https:__ ]]; then
        # Remove the _https:__domain_ portion
        # Match: _https:__ followed by domain (can contain dots, dashes) followed by _
        new_name=$(echo "$file" | sed -E 's/_https:__[^_]+_/_/')
        
        # Only rename if the name actually changed and the new file doesn't exist
        if [ "$new_name" != "$file" ] && [ ! -f "$new_name" ]; then
            mv "$file" "$new_name"
            echo "Renamed: $file -> $new_name"
        elif [ "$new_name" == "$file" ]; then
            echo "Warning: Could not parse: $file"
        elif [ -f "$new_name" ]; then
            echo "Warning: Target exists, skipping: $file"
        fi
    else
        echo "Skipping (no URL pattern): $file"
    fi
done

echo "Done renaming files."

