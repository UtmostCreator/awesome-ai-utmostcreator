#!/usr/bin/env bash
# Production-grade code search wrapper with repo-aware defaults.

set -euo pipefail

# Early --introspect / --help guard: when invoked with --introspect or --help/-h
# as the FIRST argument, emit this script's machine-readable JSON contract or its
# human-readable contract (static parse via sh-introspect) and exit before running
# any logic. The target script is parsed as text, never executed.
if [[ "${1:-}" == "--introspect" || "${1:-}" == "--help" || "${1:-}" == "-h" ]]; then
    _ai_self_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
    _ai_self_tool="$_ai_self_dir/../../tools/ai/sh-introspect.php"
    if [[ -f "$_ai_self_tool" ]] && command -v "${PHP_BIN:-php}" >/dev/null 2>&1; then
        if [[ "${1:-}" == "--introspect" ]]; then
            exec env AI_OUTPUT=json "${PHP_BIN:-php}" "$_ai_self_tool" "${BASH_SOURCE[0]}"
        fi
        exec "${PHP_BIN:-php}" "$_ai_self_tool" --format=help "${BASH_SOURCE[0]}"
    fi
fi
# shellcheck source=scripts/ai/common.sh
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

require_bins rg jq

usage() {
    cat <<'EOF'
Usage:
  rg-code.sh <pattern> [root] [options]

Modes: default | all | tracked | php | js | blade | kotlin | config
Output: --json | --files | --count
Options: --context N | --type EXT[,EXT] | --mode MODE | --ignore-case
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
IGNORE_CASE=0

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
    --ignore-case | -i)
        IGNORE_CASE=1
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

case_args=()
if [[ "$IGNORE_CASE" == "1" ]] || [[ ! "$pattern" =~ [[:upper:]] ]]; then
    case_args=(-i)
fi

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
    git -C "$root" grep "${case_args[@]}" -n "$pattern"
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
    rg "${case_args[@]}" "${mode_args[@]}" "${BASE_EXCLUDES[@]}" \
        --json -n "$pattern" "$root" |
        jq -sc '[.[] | select(.type == "match") | {
          file: .data.path.text,
          line: .data.line_number,
          col: .data.submatches[0].start,
          text: .data.lines.text
        }]'
    ;;
files)
    rg "${case_args[@]}" "${mode_args[@]}" "${BASE_EXCLUDES[@]}" -l -n "$pattern" "$root"
    ;;
count)
    rg "${case_args[@]}" "${mode_args[@]}" "${BASE_EXCLUDES[@]}" -c -n "$pattern" "$root"
    ;;
matches)
    rg "${case_args[@]}" "${mode_args[@]}" "${BASE_EXCLUDES[@]}" -n "$pattern" "$root"
    ;;
esac
