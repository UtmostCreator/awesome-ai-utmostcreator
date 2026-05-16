#!/usr/bin/env bash
# Production-grade code search wrapper with repo-aware defaults.

set -euo pipefail
# shellcheck source=scripts/ai/common.sh
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

require_bins rg jq

usage() {
    cat <<'EOF'
Usage:
  rg-code.sh <pattern> [root] [options]

Modes: default | all | tracked | php | js | blade | kotlin | config
Output: --json | --files | --count
Options: --context N | --type EXT[,EXT] | --mode MODE
EOF
}

pattern="${1:?pattern required}"
shift || true

root="."
if [[ $# -gt 0 ]] && [[ "${1:-}" != --* ]]; then
    root="$1"
    shift || true
fi

MODE="default"
CONTEXT_LINES=0
OUT_MODE="matches"
EXTRA_TYPES=()

while [[ $# -gt 0 ]]; do
    case "$1" in
    --mode)
        MODE="$2"
        shift 2
        ;;
    --mode=*)
        MODE="${1#*=}"
        shift
        ;;
    --context | -C)
        CONTEXT_LINES="$2"
        shift 2
        ;;
    --context=*)
        CONTEXT_LINES="${1#*=}"
        shift
        ;;
    --type)
        IFS=',' read -ra EXTRA_TYPES <<<"$2"
        shift 2
        ;;
    --type=*)
        IFS=',' read -ra EXTRA_TYPES <<<"${1#*=}"
        shift
        ;;
    --json)
        OUT_MODE="json"
        shift
        ;;
    --files)
        OUT_MODE="files"
        shift
        ;;
    --count)
        OUT_MODE="count"
        shift
        ;;
    --help | -h)
        usage
        exit 0
        ;;
    *) die "unknown option: $1" ;;
    esac
done

BASE_EXCLUDES=(
    -g '!vendor'
    -g '!node_modules'
    -g '!dist'
    -g '!.git'
    -g '!.repomix-context'
    -g '!*.min.js'
    -g '!*.min.css'
    -g '!package-lock.json'
    -g '!composer.lock'
    -g '!*.snap'
)

mode_args=()
case "$MODE" in
default) mode_args=(--hidden) ;;
all) mode_args=(-uuu) ;;
tracked)
    git -C "$root" grep -n "$pattern"
    exit $?
    ;;
php) mode_args=(--hidden -g '*.php') ;;
js) mode_args=(--hidden -g '*.{js,ts,jsx,tsx,mjs,cjs}') ;;
blade) mode_args=(--hidden -g '*.blade.php') ;;
kotlin) mode_args=(--hidden -g '*.{kt,kts}') ;;
config) mode_args=(--hidden -g '*.{json,yaml,yml,toml,env,env.*}') ;;
*) die "unknown mode: $MODE" ;;
esac

for ext in "${EXTRA_TYPES[@]+${EXTRA_TYPES[@]}}"; do
    mode_args+=(-g "*.$ext")
done

if ((CONTEXT_LINES > 0)); then
    mode_args+=(-C "$CONTEXT_LINES")
fi

case "$OUT_MODE" in
json)
    rg "${mode_args[@]}" "${BASE_EXCLUDES[@]}" \
        --json -n "$pattern" "$root" |
        jq -sc '[.[] | select(.type == "match") | {
          file: .data.path.text,
          line: .data.line_number,
          col: .data.submatches[0].start,
          text: .data.lines.text
        }]'
    ;;
files)
    rg "${mode_args[@]}" "${BASE_EXCLUDES[@]}" -l -n "$pattern" "$root"
    ;;
count)
    rg "${mode_args[@]}" "${BASE_EXCLUDES[@]}" -c -n "$pattern" "$root"
    ;;
matches)
    rg "${mode_args[@]}" "${BASE_EXCLUDES[@]}" -n "$pattern" "$root"
    ;;
esac
