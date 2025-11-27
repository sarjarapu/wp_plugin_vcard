#!/usr/bin/env bash

set -euo pipefail

if [[ $# -lt 2 ]]; then
  cat <<'USAGE' >&2
Usage: download_pages.sh <sites_list.tsv> <output_dir> [page_label]

The sites_list.tsv file must be tab-separated with the following columns:
  rank<TAB>site_name<TAB>url

Example:
  01<TAB>Example Dental<TAB>https://example.com/

Optional page_label defaults to "homepage" and is appended to the
generated filename (e.g., 01_example_dental_homepage.html).
USAGE
  exit 1
fi

LIST_FILE="$1"
OUTPUT_DIR="$2"
PAGE_LABEL="${3:-homepage}"

if [[ ! -f "$LIST_FILE" ]]; then
  echo "List file not found: $LIST_FILE" >&2
  exit 1
fi

mkdir -p "$OUTPUT_DIR"

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

while IFS=$'\t' read -r rank name url; do
  [[ -z "${rank// }" ]] && continue
  [[ "${rank:0:1}" == "#" ]] && continue

  if [[ -z "${name// }" || -z "${url// }" ]]; then
    echo "Skipping entry with missing name or URL (rank: $rank)" >&2
    continue
  fi

  slug=$(slugify "$name")
  rank_num=$((10#$rank))
  printf -v rank_padded "%02d" "$rank_num"
  outfile="${OUTPUT_DIR}/${rank_padded}_${slug}_${PAGE_LABEL}.html"

  echo "Downloading ${name} (${url}) -> ${outfile}"
  if ! curl --insecure -L --compressed --silent --show-error --fail "$url" -o "$outfile"; then
    echo "Failed to download ${url}, removing partial file (if any)." >&2
    rm -f "$outfile"
  fi
done < "$LIST_FILE"

