#!/usr/bin/env bash
#
# Download service pages from TSV file
# Usage: ./download_service.sh <tsv_file> <service_name>
#
# TSV Format: rank<TAB>practice_name<TAB>url
# Output: {rank}_{practice_name_slugified}_{service_name}.html

set -euo pipefail

if [[ $# -lt 2 ]]; then
  cat <<'USAGE' >&2
Usage: download_service.sh <tsv_file> <service_name>

Arguments:
  tsv_file      - Path to TSV file with rank, practice_name, url
  service_name  - Service name (e.g., teeth_whitening, dental_implants)

TSV Format:
  rank<TAB>practice_name<TAB>url

Example:
  07<TAB>Mosaic Dentistry<TAB>https://www.mosaicdentistrytx.com/services/teeth-whitening

Output:
  Files saved to: docs/websites/pages/services/{service_name}/
  File naming: {rank}_{practice_name_slugified}_{service_name}.html
USAGE
  exit 1
fi

TSV_FILE="$1"
SERVICE_NAME="$2"
OUTPUT_DIR="docs/websites/pages/services/${SERVICE_NAME}"

if [[ ! -f "$TSV_FILE" ]]; then
  echo "Error: TSV file not found: $TSV_FILE" >&2
  exit 1
fi

# Create output directory
mkdir -p "${OUTPUT_DIR}"

# Slugify function (from download_pages.sh)
slugify() {
  local input="$1"
  INPUT_NAME="$input" python3 - <<'PY'
import os
import re
import unicodedata

name = os.environ.get("INPUT_NAME", "")
normalized = unicodedata.normalize("NFKD", name)
ascii_only = normalized.encode("ascii", "ignore").decode("ascii")
slug = re.sub(r"[^a-z0-9]+", "_", ascii_only.lower()).strip("_")
print(slug or "site")
PY
}

echo "=== Downloading Service Pages ==="
echo "Service: ${SERVICE_NAME}"
echo "TSV File: ${TSV_FILE}"
echo "Output Directory: ${OUTPUT_DIR}"
echo ""

success_count=0
fail_count=0

# Download each URL
while IFS=$'\t' read -r rank name url; do
  [[ -z "${rank// }" ]] && continue
  [[ "${rank:0:1}" == "#" ]] && continue

  if [[ -z "${name// }" || -z "${url// }" ]]; then
    echo "⚠ Skipping entry with missing name or URL (rank: $rank)" >&2
    ((fail_count++)) || true
    continue
  fi

  slug=$(slugify "$name")
  rank_num=$((10#$rank))
  printf -v rank_padded "%02d" "$rank_num"
  outfile="${OUTPUT_DIR}/${rank_padded}_${slug}_${SERVICE_NAME}.html"

  echo "Downloading ${rank}: ${name}"
  echo "  URL: ${url}"
  echo "  Saving as: $(basename ${outfile})"
  
  if curl --insecure -L --compressed --silent --show-error --fail --max-time 30 "$url" -o "$outfile" 2>/dev/null; then
    # Verify it's HTML
    if [[ -f "$outfile" ]] && [[ -s "$outfile" ]]; then
      if head -n 10 "$outfile" | grep -qi "<html\|<!DOCTYPE\|<body\|<head"; then
        file_size=$(wc -c < "$outfile" | tr -d ' ')
        echo "  ✓ Downloaded successfully (${file_size} bytes)"
        ((success_count++)) || true
      else
        echo "  ⚠ Downloaded but may not be valid HTML"
        ((fail_count++)) || true
        rm -f "$outfile"
      fi
    else
      echo "  ✗ Downloaded file is empty"
      ((fail_count++)) || true
      rm -f "$outfile"
    fi
  else
    echo "  ✗ Failed to download"
    rm -f "$outfile"
    ((fail_count++)) || true
  fi
  
  echo ""
done < "$TSV_FILE"

echo "=== Download Complete ==="
echo "Success: ${success_count}"
echo "Failed: ${fail_count}"
echo "Total files: $(ls -1 ${OUTPUT_DIR}/*.html 2>/dev/null | wc -l | tr -d ' ')"
echo "Files saved to: ${OUTPUT_DIR}"

if [[ ${success_count} -eq 0 ]]; then
  echo "Warning: No files were downloaded successfully!" >&2
  exit 1
fi

