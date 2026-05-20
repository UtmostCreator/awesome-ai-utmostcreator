#!/usr/bin/env bash
# Repo-aware file discovery wrapper.

set -euo pipefail
# shellcheck source=scripts/ai/common.sh
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

require_bins jq

usage() {
    cat <<'EOF'
Usage:
  fd-files.sh QUERY [root] [--json] [--hidden] [--type EXT[,EXT]]
EOF
}

if [[ "${1:-}" == "--help" || "${1:-}" == "-h" ]]; then
    usage
    exit 0
fi

if [[ $# -lt 1 ]]; then
    usage >&2
    exit 1
fi

query="$1"
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

fd_bin="$(find_fd_bin || true)"

run_discovery() {
    if [[ -n "$fd_bin" ]]; then
        "$fd_bin" "${args[@]}" "$query" "$root"
        return
    fi

    require_bins rg
    local rg_args=(
        --files
        -g '!vendor/**'
        -g '!node_modules/**'
        -g '!dist/**'
        -g '!.git/**'
        -g '!.repomix-context/**'
    )

    if [[ "$INCLUDE_HIDDEN" == "1" ]]; then
        rg_args+=(--hidden)
    fi

    local path base ext wanted include
    while IFS= read -r path; do
        base="${path##*/}"
        [[ "$base" == *"$query"* || "$path" == *"$query"* ]] || continue

        if [[ ${#EXTRA_TYPES[@]} -gt 0 ]]; then
            ext="${base##*.}"
            include=0
            for wanted in "${EXTRA_TYPES[@]}"; do
                if [[ "$ext" == "$wanted" ]]; then
                    include=1
                    break
                fi
            done
            [[ "$include" == "1" ]] || continue
        fi

        printf '%s\n' "$path"
    done < <(rg "${rg_args[@]}" "$root")
}

if [[ "$OUTPUT_FORMAT" == "json" ]]; then
    run_discovery | jq -R . | jq -s .
else
    run_discovery
fi
