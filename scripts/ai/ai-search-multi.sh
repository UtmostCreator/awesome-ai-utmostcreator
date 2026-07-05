#!/usr/bin/env bash
# Batch wrapper around ai-search.sh: run one safe search MODE against several
# queries in a single approved invocation.
#
# Security model: this script never interprets shell metacharacters from its
# arguments. Each QUERY is passed to ai-search.sh as a single quoted positional
# argument via a normal process call (no eval, no `sh -c`, no string building).

set -euo pipefail

# shellcheck disable=SC1091
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

BASH_BIN="${BASH_BIN:-$(command -v bash)}"
SEARCH="$(dirname "${BASH_SOURCE[0]}")/ai-search.sh"
MAX_QUERIES="${AI_SEARCH_MULTI_MAX:-20}"

usage() {
    cat <<'EOF'
Usage:
  ai-search-multi.sh MODE QUERY [QUERY ...] [root] [flags]
  ai-search-multi.sh FILE_LIST_MODE [root] [flags]
  ai-search-multi.sh LEGACY_FILE_LIST_MODE [legacy-query] [root] [flags]

Runs ai-search.sh MODE once per QUERY in a single invocation.

Query modes:
  changed-text | staged-text | tracked | text | files | struct | docs
  diff | history | tests | config | deps | symbols | class
  function | method | interface | enum | route | config-key

File-list modes:
  changed-files | staged-files

Deprecated aliases:
  changed | staged

No-query curated modes:
  todo | unsafe-patterns

Rejected:
  unsafe-all

Notes:
  - For query modes, root is optional and, if given, must be the LAST positional
    value before flags. When omitted, "." is used.
  - For file-list/no-query modes, pass only optional root.
  - Legacy changed/staged may also be called as: changed dummy ROOT.
  - Flags are forwarded to ai-search.sh.
  - Set AI_OUTPUT=json for a JSON array of per-query ai-search envelopes.
  - At most AI_SEARCH_MULTI_MAX queries for query modes (default 20).

Examples:
  ai-search-multi.sh files niri vicinae .
  AI_OUTPUT=json ai-search-multi.sh text foo bar . --fixed
  AI_OUTPUT=json ai-search-multi.sh changed-files .
  AI_OUTPUT=json ai-search-multi.sh changed old-dummy .
EOF
}

json_mode="${AI_OUTPUT:-}"
mode="${1:-}"

if [[ "$mode" == "--help" || "$mode" == "-h" || -z "$mode" ]]; then
    usage
    exit 0
fi
shift

mode_family=""

case "$mode" in
changed-text | staged-text | tracked | text | files | struct | docs)
    mode_family="query"
    ;;
diff | history | tests | config | deps | symbols | class)
    mode_family="query"
    ;;
function | method | interface | enum | route | config-key)
    mode_family="query"
    ;;
changed-files | staged-files)
    mode_family="file-list"
    ;;
changed | staged)
    mode_family="legacy-file-list"
    ;;
todo | unsafe-patterns)
    mode_family="no-query-root"
    ;;
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

# Collect positional values until the first flag.
positionals=()
while [[ $# -gt 0 && "${1:-}" != --* && "${1:-}" != "-i" ]]; do
    positionals+=("$1")
    shift
done

# Remaining args are flags forwarded to ai-search.sh.
flags=("$@")

run_one() {
    "$BASH_BIN" "$SEARCH" "$@" "${flags[@]}"
}

emit_single_result_array() {
    local inner_mode="$1"
    local inner_root="$2"
    local output
    local rc

    if [[ "$json_mode" == "json" ]]; then
        set +e
        output="$(AI_OUTPUT=json run_one "$inner_mode" "$inner_root" 2>&1)"
        rc=$?
        set -e

        printf '[%s]\n' "$output"
        return "$rc"
    fi

    run_one "$inner_mode" "$inner_root"
}

emit_legacy_result_array() {
    local inner_mode="$1"
    local legacy_query="$2"
    local inner_root="$3"
    local output
    local rc

    if [[ "$json_mode" == "json" ]]; then
        set +e
        output="$(AI_OUTPUT=json run_one "$inner_mode" "$legacy_query" "$inner_root" 2>&1)"
        rc=$?
        set -e

        printf '[%s]\n' "$output"
        return "$rc"
    fi

    run_one "$inner_mode" "$legacy_query" "$inner_root"
}

# Canonical file-list modes:
#   changed-files [root]
#   staged-files [root]
if [[ "$mode_family" == "file-list" ]]; then
    root="."

    case "${#positionals[@]}" in
    0)
        root="."
        ;;
    1)
        root="${positionals[0]}"
        ;;
    *)
        echo "[ERROR] mode '$mode' does not accept queries; pass only optional root" >&2
        exit 1
        ;;
    esac

    emit_single_result_array "$mode" "$root"
    exit $?
fi

# Legacy aliases:
#   changed [legacy-query] [root]
#   staged [legacy-query] [root]
#
# Keep old dummy-query shape working during migration:
#   changed dummy .
#   staged dummy .
if [[ "$mode_family" == "legacy-file-list" ]]; then
    legacy_query="__legacy_alias__"
    root="."

    case "${#positionals[@]}" in
    0)
        root="."
        ;;
    1)
        if [[ -d "${positionals[0]}" ]]; then
            root="${positionals[0]}"
        else
            legacy_query="${positionals[0]}"
            root="."
        fi
        ;;
    2)
        legacy_query="${positionals[0]}"
        root="${positionals[1]}"
        ;;
    *)
        echo "[ERROR] too many positional arguments for legacy mode '$mode'" >&2
        exit 1
        ;;
    esac

    emit_legacy_result_array "$mode" "$legacy_query" "$root"
    exit $?
fi

# No-query curated modes:
#   todo [root]
#   unsafe-patterns [root]
if [[ "$mode_family" == "no-query-root" ]]; then
    root="."

    case "${#positionals[@]}" in
    0)
        root="."
        ;;
    1)
        root="${positionals[0]}"
        ;;
    *)
        echo "[ERROR] mode '$mode' does not accept queries; pass only optional root" >&2
        exit 1
        ;;
    esac

    emit_single_result_array "$mode" "$root"
    exit $?
fi

# Query modes:
#   MODE QUERY [QUERY ...] [root] [flags]
queries=("${positionals[@]}")

# Optional trailing root: if the last collected positional is an existing
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
    echo "[ERROR] at least one QUERY is required for mode '$mode'" >&2
    usage >&2
    exit 1
fi

if [[ ${#queries[@]} -gt "$MAX_QUERIES" ]]; then
    echo "[ERROR] too many queries (${#queries[@]} > $MAX_QUERIES); raise AI_SEARCH_MULTI_MAX to override" >&2
    exit 1
fi

if [[ "$json_mode" == "json" ]]; then
    first=1
    overall_rc=0

    printf '['

    for q in "${queries[@]}"; do
        output=""
        rc=0

        set +e
        output="$(AI_OUTPUT=json run_one "$mode" "$q" "$root" 2>&1)"
        rc=$?
        set -e

        [[ "$first" == "1" ]] || printf ','
        first=0
        printf '%s' "$output"

        if [[ "$rc" -ne 0 ]]; then
            overall_rc="$rc"
        fi
    done

    printf ']\n'
    exit "$overall_rc"
fi

first=1
for q in "${queries[@]}"; do
    [[ "$first" == "1" ]] || printf '%s\n' "---"
    first=0
    run_one "$mode" "$q" "$root"
done
