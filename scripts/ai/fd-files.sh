#!/usr/bin/env bash
# Repo-aware file discovery wrapper.

set -euo pipefail
# shellcheck source=scripts/ai/common.sh
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

require_bins fd jq

usage() {
    cat <<'EOF'
Usage:
  fd-files.sh QUERY [root] [--json] [--hidden] [--type EXT[,EXT]]
EOF
}

query="${1:?query required}"
shift || true

root="."
if [[ $# -gt 0 ]] && [[ "${1:-}" != --* ]]; then
    root="$1"
    shift || true
fi

OUTPUT_FORMAT="plain"
INCLUDE_HIDDEN=0
EXTRA_TYPES=()

while [[ $# -gt 0 ]]; do
    case "$1" in
    --json)
        OUTPUT_FORMAT="json"
        shift
        ;;
    --hidden)
        INCLUDE_HIDDEN=1
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
    --help | -h)
        usage
        exit 0
        ;;
    *) die "unknown option: $1" ;;
    esac
done

args=(
    -E vendor
    -E node_modules
    -E dist
    -E .git
    -E .repomix-context
)

if [[ "$INCLUDE_HIDDEN" == "1" ]]; then
    args+=(--hidden)
fi

for ext in "${EXTRA_TYPES[@]+${EXTRA_TYPES[@]}}"; do
    args+=(-e "$ext")
done

if [[ "$OUTPUT_FORMAT" == "json" ]]; then
    fd "${args[@]}" "$query" "$root" | jq -R . | jq -s .
else
    fd "${args[@]}" "$query" "$root"
fi
