#!/usr/bin/env bash
# Batch wrapper around ai-search.sh: run one safe search MODE against several
# queries in a single approved invocation.
#
# Security model: this script never interprets shell metacharacters from its
# arguments. Each QUERY is passed to ai-search.sh as a single quoted positional
# argument via a normal process call (no eval, no `sh -c`, no string building).
# Therefore an allow rule of `bash scripts/ai/ai-search-multi.sh *` cannot be
# used to smuggle a second command: characters like `;`, `|`, `&&` become
# literal query text, not shell operators.

set -euo pipefail
# shellcheck source=scripts/ai/common.sh
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

SEARCH="$(dirname "${BASH_SOURCE[0]}")/ai-search.sh"
MAX_QUERIES="${AI_SEARCH_MULTI_MAX:-20}"

usage() {
    cat <<'EOF'
Usage:
  ai-search-multi.sh MODE QUERY [QUERY ...] [root] [--fixed] [--ignore-case]

Runs ai-search.sh MODE once per QUERY in a single invocation.

Modes (safe subset; unsafe-all is rejected):
  changed | staged | tracked | text | files | struct | docs

Notes:
  - `root` is optional and, if given, must be the LAST positional value before
    any flags. When omitted, "." is used.
  - Flags (--fixed, --ignore-case/-i) are forwarded to every query.
  - Set AI_OUTPUT=json for a JSON array of per-query ai-search envelopes.
  - At most AI_SEARCH_MULTI_MAX queries (default 20).

Examples:
  ai-search-multi.sh files niri vicinae .
  AI_OUTPUT=json ai-search-multi.sh text foo bar . --fixed
EOF
}

json_mode="${AI_OUTPUT:-}"
mode="${1:-}"

if [[ "$mode" == "--help" || "$mode" == "-h" || -z "$mode" ]]; then
    usage
    exit 0
fi
shift

case "$mode" in
changed | staged | tracked | text | files | struct | docs) ;;
unsafe-all)
    echo "[ERROR] unsafe-all is not allowed via ai-search-multi.sh" >&2
    exit 1
    ;;
*)
    echo "[ERROR] unknown or unsupported mode: $mode" >&2
    usage >&2
    exit 1
    ;;
esac

# Collect positional queries until the first flag.
queries=()
while [[ $# -gt 0 && "${1:-}" != --* && "${1:-}" != "-i" ]]; do
    queries+=("$1")
    shift
done

# Remaining args are flags forwarded to ai-search.sh.
flags=("$@")

# Optional trailing `root`: if the last collected positional is an existing
# directory and more than one positional was given, treat it as root.
root="."
if [[ ${#queries[@]} -gt 1 ]]; then
    last_index=$((${#queries[@]} - 1))
    last="${queries[$last_index]}"
    if [[ -d "$last" ]]; then
        root="$last"
        unset 'queries[last_index]'
        queries=("${queries[@]}")
    fi
fi

if [[ ${#queries[@]} -eq 0 ]]; then
    echo "[ERROR] at least one QUERY is required" >&2
    usage >&2
    exit 1
fi

if [[ ${#queries[@]} -gt "$MAX_QUERIES" ]]; then
    echo "[ERROR] too many queries (${#queries[@]} > $MAX_QUERIES); raise AI_SEARCH_MULTI_MAX to override" >&2
    exit 1
fi

if [[ "$json_mode" == "json" ]]; then
    first=1
    printf '['
    for q in "${queries[@]}"; do
        [[ "$first" == "1" ]] || printf ','
        first=0
        AI_OUTPUT=json bash "$SEARCH" "$mode" "$q" "$root" "${flags[@]+${flags[@]}}"
    done
    printf ']\n'
else
    first=1
    for q in "${queries[@]}"; do
        [[ "$first" == "1" ]] || printf '%s\n' "---"
        first=0
        bash "$SEARCH" "$mode" "$q" "$root" "${flags[@]+${flags[@]}}"
    done
fi
