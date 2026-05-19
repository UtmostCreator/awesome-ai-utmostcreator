#!/usr/bin/env bash
# Smart preview wrapper with text and fallback modes.

set -euo pipefail
# shellcheck source=scripts/ai/common.sh
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

usage() {
    cat <<'EOF'
Usage:
  preview-file.sh FILE [--plain] [--lines N]
EOF
}

file="${1:?file required}"
shift || true

[[ -f "$file" ]] || die "file not found: $file"

PLAIN=0
LINES=""

while [[ $# -gt 0 ]]; do
    case "$1" in
    --plain)
        PLAIN=1
        shift
        ;;
    --lines)
        LINES="$2"
        shift 2
        ;;
    --lines=*)
        LINES="${1#*=}"
        shift
        ;;
    --help | -h)
        usage
        exit 0
        ;;
    *) die "unknown option: $1" ;;
    esac
done

bat_args=(--style=numbers --color=always)
if [[ -n "$LINES" ]]; then
    bat_args+=(--line-range ":$LINES")
fi

if [[ "$PLAIN" == "0" ]] && command -v bat >/dev/null 2>&1; then
    bat "${bat_args[@]}" "$file"
    exit 0
fi

if [[ -n "$LINES" ]]; then
    sed -n "1,${LINES}p" "$file"
else
    sed -n '1,200p' "$file"
fi
