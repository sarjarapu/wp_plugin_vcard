#!/usr/bin/env bash
#
# Generic script to download competitor pages from sitemaps
# This script combines URL extraction and downloading into one workflow
#
# Usage:
#   ./download_competitor_pages.sh <page_type> <output_dir> [page_label]
#
# Examples:
#   # Download service listing pages
#   ./download_competitor_pages.sh services_listing docs/websites/pages/services/listing services_listing
#
#   # Download team pages
#   ./download_competitor_pages.sh team docs/websites/pages/team team
#
#   # Download doctor profile pages
#   ./download_competitor_pages.sh doctor_profiles docs/websites/pages/doctors doctor_profile
#
#   # Download with custom pattern
#   ./download_competitor_pages.sh custom docs/websites/pages/custom custom_page '/custom-pattern/'

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
EXTRACT_SCRIPT="${SCRIPT_DIR}/extract_urls_from_sitemaps.py"
DOWNLOAD_SCRIPT="${SCRIPT_DIR}/download_pages.sh"

if [[ $# -lt 2 ]]; then
  cat <<'USAGE' >&2
Usage: download_competitor_pages.sh <page_type> <output_dir> [page_label] [custom_pattern]

Arguments:
  page_type      - Type of page (services_listing, team, doctor_profiles, about, contact, or custom)
  output_dir     - Directory to save downloaded HTML files
  page_label     - Label for downloaded files (default: same as page_type)
  custom_pattern - Optional custom regex pattern (only used with page_type='custom')

Examples:
  ./download_competitor_pages.sh services_listing docs/websites/pages/services/listing services_listing
  ./download_competitor_pages.sh team docs/websites/pages/team team
  ./download_competitor_pages.sh custom docs/websites/pages/custom custom_page '/custom-pattern/'
USAGE
  exit 1
fi

PAGE_TYPE="$1"
OUTPUT_DIR="$2"
PAGE_LABEL="${3:-${PAGE_TYPE}}"
CUSTOM_PATTERN="${4:-}"

# Create temporary TSV file
TEMP_TSV=$(mktemp)
trap "rm -f ${TEMP_TSV}" EXIT

echo "=== Step 1: Extracting URLs from sitemaps ==="
echo "Page type: ${PAGE_TYPE}"
echo "Output TSV: ${TEMP_TSV}"
echo ""

if [[ -n "${CUSTOM_PATTERN}" ]]; then
  python3 "${EXTRACT_SCRIPT}" "${PAGE_TYPE}" "${TEMP_TSV}" "${CUSTOM_PATTERN}"
else
  python3 "${EXTRACT_SCRIPT}" "${PAGE_TYPE}" "${TEMP_TSV}"
fi

if [[ ! -s "${TEMP_TSV}" ]]; then
  echo "Error: No URLs found. Exiting." >&2
  exit 1
fi

echo ""
echo "=== Step 2: Downloading pages ==="
echo "Output directory: ${OUTPUT_DIR}"
echo "Page label: ${PAGE_LABEL}"
echo ""

bash "${DOWNLOAD_SCRIPT}" "${TEMP_TSV}" "${OUTPUT_DIR}" "${PAGE_LABEL}"

echo ""
echo "=== Complete ==="
echo "Downloaded files saved to: ${OUTPUT_DIR}"
echo "Total files: $(ls -1 "${OUTPUT_DIR}"/*.html 2>/dev/null | wc -l | tr -d ' ')"

