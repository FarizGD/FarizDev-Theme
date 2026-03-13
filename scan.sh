#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="${1:-/var/lib/pterodactyl/volumes}"

if [[ ! -d "$ROOT_DIR" ]]; then
  echo "Root directory not found: $ROOT_DIR" >&2
  exit 1
fi

# Case-insensitive terms (filename or content)
TERMS=(
  "deface"
  "hack"
  "kill panel"
  "kill"
  "killer"
  "rangga"
  "filler"
  ".bin"
  "disk filler"
  "buffer random"
  "steal data"
)

# Build a case-insensitive regex for filenames
NAME_REGEX=$(printf '%s|' "${TERMS[@]}")
NAME_REGEX="${NAME_REGEX%|}"

# Delete files with blocked terms in the name (exclude node_modules)
while IFS= read -r -d '' file; do
  echo "[name-match] Deleting: $file"
  rm -f -- "$file"
done < <(
  find "$ROOT_DIR" \
    -type d -name node_modules -prune -o \
    -type f -iregex ".*(${NAME_REGEX}).*" -print0
)

# Delete files with blocked terms in the content
for term in "${TERMS[@]}"; do
  # Use grep to find files containing the term (text-ish files only)
  while IFS= read -r file; do
    if [[ -f "$file" ]]; then
      echo "[content-match] Deleting: $file (term: $term)"
      rm -f -- "$file"
    fi
  done < <(
    grep -RIl --binary-files=without-match -F -i \
      --exclude-dir=node_modules \
      -- "$term" "$ROOT_DIR" || true
  )
done

echo "Scan complete."
