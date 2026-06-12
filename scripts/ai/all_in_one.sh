#!/bin/zsh
# all_in_one.sh (combine_files.sh)
# Recursively collects filenames and contents, writes them to a single output file at project root.
# Prunes ignored directories (entire subtrees) and excludes selected files.
# Each file block (header + content + footer) is wrapped inside triple backticks.

set -euo pipefail

# Early --introspect guard: emit this script's machine-readable JSON contract
# (static parse via sh-introspect) and exit before running any logic. zsh-safe
# self-path via $0. The target script is parsed as text, never executed.
if [[ "${1:-}" == "--introspect" ]]; then
    _ai_introspect_self="$0"
    _ai_introspect_here="$(cd "$(dirname "$_ai_introspect_self")" && pwd)"
    _ai_introspect_tool="$_ai_introspect_here/../../tools/ai/sh-introspect.php"
    if [[ -f "$_ai_introspect_tool" ]] && command -v "${PHP_BIN:-php}" >/dev/null 2>&1; then
        exec env AI_OUTPUT=json "${PHP_BIN:-php}" "$_ai_introspect_tool" "$_ai_introspect_self"
    fi
fi

ROOT_DIR="$(pwd)"
OUTPUT_FILE="${ROOT_DIR}/combined_output.txt"

# Directories to ignore (by directory NAME at any depth). Entire trees are pruned.
IGNORE_DIRS=(
  ".git"
  "node_modules"
  ".venv"
  "dist"
  "build"
  ".next"
)

# Files to ignore by basename (name match anywhere)
IGNORE_FILE_NAMES=(
  ".DS_Store"
)

# Files to ignore by path suffix (relative-ending match)
# Examples:
#   "package-lock.json"
#   "yarn.lock"
#   "src/config/local.env"
IGNORE_FILE_PATHS=(
  # "package-lock.json"
  # "yarn.lock"
  # "src/config/local.env"
)

# Rotate old output
if [[ -f "$OUTPUT_FILE" ]]; then
  mv -- "$OUTPUT_FILE" "${OUTPUT_FILE}.bak.$(date +%Y%m%d-%H%M%S)"
fi

# Create empty output file
: > "$OUTPUT_FILE"

append_file() {
  local file="$1"
  local rel="${file#$ROOT_DIR/}"
  {
    printf '```\n'
    printf '===== START FILE: %s =====\n' "$rel"
    cat -- "$file"
    printf '\n===== END FILE: %s =====\n' "$rel"
    printf '```\n\n'
  } >> "$OUTPUT_FILE"
}

# Build find arguments into the global `reply` array.
build_find_args() {
  local -a dir_prunes=() file_name_neg=() file_path_neg=()

  # Directory prune clause
  if (( ${#IGNORE_DIRS[@]} > 0 )); then
    local d
    dir_prunes+=( '(' )
    for d in "${IGNORE_DIRS[@]}"; do
      dir_prunes+=( -type d -name "$d" -o )
    done
    dir_prunes[-1]=')'             # replace last -o
    dir_prunes+=( -prune -o )
  fi

  # File name negation clause
  if (( ${#IGNORE_FILE_NAMES[@]} > 0 )); then
    local n
    file_name_neg+=( '(' )
    for n in "${IGNORE_FILE_NAMES[@]}"; do
      file_name_neg+=( -name "$n" -o )
    done
    file_name_neg[-1]=')'          # replace last -o
  fi

  # File path negation clause
  if (( ${#IGNORE_FILE_PATHS[@]} > 0 )); then
    local p
    for p in "${IGNORE_FILE_PATHS[@]}"; do
      file_path_neg+=( -path "*/$p" -o )
    done
    file_path_neg[-1]=')'           # replace last -o
  fi

  # File clause
  local -a file_clause=( -type f )
  if (( ${#file_name_neg[@]} > 0 || ${#file_path_neg[@]} > 0 )); then
    file_clause+=( '!' \( )
    if (( ${#file_name_neg[@]} > 0 )); then
      file_clause+=( "${file_name_neg[@]}" )
      if (( ${#file_path_neg[@]} > 0 )); then
        file_clause+=( -o "${file_path_neg[@]}" )
      fi
    else
      file_clause+=( "${file_path_neg[@]}" )
    fi
    file_clause+=( \) )
  fi
  file_clause+=( -print0 )

  reply=( ${dir_prunes[@]+"${dir_prunes[@]}"} "${file_clause[@]}" )
}

# Prepare find args
declare -a FIND_ARGS
build_find_args
FIND_ARGS=( "${reply[@]}" )

# Collect files and append
while IFS= read -r -d '' f; do
  [[ "$f" == "$OUTPUT_FILE" ]] && continue
  append_file "$f"
done < <( find "$ROOT_DIR" "${FIND_ARGS[@]}" )

# macOS notification (no-op on other systems)
if command -v osascript >/dev/null 2>&1; then
  osascript -e 'display notification "Combined file created successfully." with title "Combine Files"'
fi

echo "Success: Combined file created at: $OUTPUT_FILE"
