#!/usr/bin/env bash
# shellcheck disable=SC2016
set -euo pipefail

usage() {
    cat <<'EOF'
Usage:
  scripts/ai/query-usage.sh [path] [--multiplier <n>] [--multiplier-label <label>] [--reserved-output <n>]

Print a read-only usage closeout summary for inspected content.
EOF
}

TARGET='.'
MULTIPLIER='1'
LABEL='1x'
RESERVED_OUTPUT='4000'

if (($# > 0)) && [[ "${1:-}" != --* ]]; then
    TARGET="$1"
    shift || true
fi

while (($# > 0)); do
    case "$1" in
    --multiplier) MULTIPLIER="$2"; shift 2 ;;
    --multiplier=*) MULTIPLIER="${1#*=}"; shift ;;
    --multiplier-label) LABEL="$2"; shift 2 ;;
    --multiplier-label=*) LABEL="${1#*=}"; shift ;;
    --reserved-output) RESERVED_OUTPUT="$2"; shift 2 ;;
    --reserved-output=*) RESERVED_OUTPUT="${1#*=}"; shift ;;
    --help | -h) usage; exit 0 ;;
    *) echo "Unknown option: $1" >&2; usage; exit 2 ;;
    esac
done

[[ -e "$TARGET" ]] || {
    echo "Path not found: $TARGET" >&2
    exit 1
}

if [[ -d "$TARGET" ]]; then
    BYTES="$(git -C "$TARGET" ls-files -z 2>/dev/null | xargs -0 -I{} sh -c 'test -f "$1" && wc -c <"$1" || true' _ "$TARGET/{}" | awk '{s+=$1} END{print s+0}')"
    if [[ "$BYTES" == "0" ]]; then
        BYTES="$(rg --files "$TARGET" 2>/dev/null | xargs -I{} sh -c 'wc -c <"$1"' _ {} 2>/dev/null | awk '{s+=$1} END{print s+0}')"
    fi
else
    BYTES="$(wc -c < "$TARGET")"
fi

RAW_TOKENS="$(awk -v b="$BYTES" 'BEGIN { printf "%d", int((b + 3) / 4) }')"
WEIGHTED="$(awk -v t="$RAW_TOKENS" -v m="$MULTIPLIER" 'BEGIN { printf "%.2f", t * m }')"

cat <<EOF
query_usage:
  path: $TARGET
  bytes: $BYTES
  raw_estimated_tokens: $RAW_TOKENS
  multiplier_label: $LABEL
  multiplier: $MULTIPLIER
  weighted_usage: $WEIGHTED
  reserved_output_tokens: $RESERVED_OUTPUT
EOF
